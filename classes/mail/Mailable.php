<?php

/**
 * @file classes/mail/Mailable.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Mailable
 *
 * @ingroup mail
 *
 * @brief  A base class that represents an email sent in the system.
 *
 * This class extends Laravel's mailable class, which provides methods to
 * configure and send email. This class overrides Laravel's support for message
 * templates that use blade or markdown templates. Instead, it supports PKP's
 * email templates system.
 *
 * Each email sent by the system which uses an email template should extend
 * this class. The arguments in the constructor method are used to define the
 * variables that can be used in the corresponding email templates. This mailable
 * class provides helper methods to compile the variables and render the email
 * subject and body.
 *
 */

namespace PKP\mail;

use APP\decision\Decision;
use APP\facades\Repo;
use APP\mail\variables\ContextEmailVariable;
use APP\mail\variables\SubmissionEmailVariable;
use BadMethodCallException;
use Exception;
use Illuminate\Mail\Mailable as IlluminateMailable;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use PKP\config\Config;
use PKP\context\Context;
use PKP\context\LibraryFile;
use PKP\context\LibraryFileDAO;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\file\TemporaryFileManager;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;
use PKP\mail\variables\DecisionEmailVariable;
use PKP\mail\variables\QueuedPaymentEmailVariable;
use PKP\mail\variables\RecipientEmailVariable;
use PKP\mail\variables\ReviewAssignmentEmailVariable;
use PKP\mail\variables\SenderEmailVariable;
use PKP\mail\variables\SiteEmailVariable;
use PKP\mail\variables\Variable;
use PKP\payment\QueuedPayment;
use PKP\site\Site;
use PKP\submission\PKPSubmission;
use PKP\submission\reviewAssignment\ReviewAssignment;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

class Mailable extends IlluminateMailable
{
    public const EMAIL_TEMPLATE_STYLE_PROPERTY = 'emailTemplateStyle';

    /** Used internally by Illuminate Mailer. Do not touch. */
    public const DATA_KEY_MESSAGE = 'message';

    /** Data key for the context associated with the Mailable */
    public const DATA_KEY_CONTEXT = 'context';

    public const GROUP_OTHER = 'other';
    public const GROUP_SUBMISSION = 'submission';
    public const GROUP_REVIEW = 'review';
    public const GROUP_COPYEDITING = 'copyediting';
    public const GROUP_PRODUCTION = 'production';

    /**
     * A dummy "role" for the Sent From filters in the mailable UI.
     *
     * Mailables sent from this "role" are sent by the system itself.
     */
    public const FROM_SYSTEM = -1;

    public const ATTACHMENT_TEMPORARY_FILE = 'temporaryFileId';
    public const ATTACHMENT_SUBMISSION_FILE = 'submissionFileId';
    public const ATTACHMENT_LIBRARY_FILE = 'libraryFileId';

    /** @var string|null The specific locale to send the mail of this Mailable */
    protected ?string $mailableLocale = null;

    /** @var string|null Locale key for the name of this Mailable */
    protected static ?string $name = null;

    /** @var string|null Locale key for the description of this Mailable */
    protected static ?string $description = null;

    /** @var string|null Key of the default email template key to use with this Mailable */
    protected static ?string $emailTemplateKey = null;

    /** @var bool Whether users can assign extra email templates to this Mailable */
    protected static bool $supportsTemplates = false;

    /** @var int[] Mailables are organized into one or more self::GROUP_ */
    protected static array $groupIds = [self::GROUP_OTHER];

    /** @var bool Whether user can disable this Mailable */
    protected static bool $canDisable = false;

    /** @var int[] Role ID of the sender, see Role::ROLE_ID_ constants */
    protected static array $fromRoleIds = [];

    /** @var int[] Role ID of the recipient(s) see Role::ROLE_ID_ constants */
    protected static array $toRoleIds = [];

    /** @var Variable[] The email variables supported by this mailable */
    protected array $variables = [];

    /** @var string embedded footer of the email */
    protected string $footer;

    public function __construct(array $variables = [])
    {
        if (!empty($variables)) {
            $this->setupVariables($variables);
        }
    }

    /**
     * Get the name of this Mailable
     */
    public static function getName(): string
    {
        return static::$name ? __(static::$name) : '';
    }

    /**
     * Get the description of this Mailable
     */
    public static function getDescription(): string
    {
        return static::$description ? __(static::$description) : '';
    }

    /**
     * Get the description of this Mailable
     */
    public static function getEmailTemplateKey(): string
    {
        return static::$emailTemplateKey ? static::$emailTemplateKey : '';
    }

    /**
     * Get whether or not this Mailable supports extra email templates
     */
    public static function getSupportsTemplates(): bool
    {
        return static::$supportsTemplates;
    }

    /**
     * Get the groups this Mailable is in
     *
     * @return string[]
     */
    public static function getGroupIds(): array
    {
        return static::$groupIds;
    }

    /**
     * Get role IDs of users that are able to send the Mailable
     */
    public static function getFromRoleIds(): array
    {
        return static::$fromRoleIds;
    }

    /**
     * Get role IDs of recipients
     */
    public static function getToRoleIds(): array
    {
        return static::$toRoleIds;
    }

    /**
     * Add data for this email
     */
    public function addData(array $data): self
    {
        $this->viewData = array_merge($this->viewData, $data);
        return $this;
    }

    /**
     * Alias of self::addData()
     *
     * @see \Illuminate\Mail\Mailable::with()
     *
     * @param null|mixed $value
     */
    public function with($key, $value = null)
    {
        if (is_array($key)) {
            return $this->addData($key);
        }

        return $this->addData([$key => $value]);
    }

    /**
     * Get the data for this email
     */
    public function getData(?string $locale = null): array
    {
        $this->setData($locale);
        return $this->viewData;
    }

    /**
     * Set the data for this email
     */
    public function setData(?string $locale = null): void
    {
        if (is_null($locale)) {
            $locale = $this->getLocale() ?? Locale::getLocale();
        }
        foreach ($this->variables as $variable) {
            $this->viewData = array_merge(
                $this->viewData,
                $variable->values($locale)
            );
        }

        $this->addFooter($locale); // set the locale for the email footer
    }

    /**
     * Set the mailable locale
     */
    public function setLocale(string $locale): static
    {
        $this->mailableLocale = $locale;

        return $this;
    }

    /**
     * Get the mailable locale
     */
    public function getLocale(): ?string
    {
        return $this->mailableLocale;
    }

    /**
     * @param string $localeKey
     *
     * @throws BadMethodCallException
     */
    public function locale($localeKey)
    {
        throw new BadMethodCallException('This method isn\'t supported, data passed to ' . static::class .
            ' should already by localized.');
    }

    /**
     * Use instead of the \Illuminate\Mail\Mailable::view() to compile template message's body
     *
     * @param string $view HTML string with template variables
     */
    public function body(?string $view): self
    {
        return parent::view($view ?? '', []);
    }

    /**
     * Multiple From addresses per mailbox-list syntax according to RFC 5322 isn't supported by PHPMailer transport
     *
     * @copydoc Illuminate\Mail\Mailable::from()
     *
     * @param null|mixed $name
     */
    public function from($address, $name = null)
    {
        if (
            (is_array($address) && count($address) > 1)
            ||
            ($address instanceof Collection && $address->count() > 1)
        ) {
            trigger_error(
                'Mailbox-list syntax in the From field isn\'t supported by PHPMailer transport',
                E_USER_WARNING
            );
        }

        return parent::from($address, $name);
    }

    /**
     * Doesn't support Illuminate markdown
     *
     * @throws BadMethodCallException
     */
    public function markdown($view, array $data = []): self
    {
        throw new BadMethodCallException('Markdown isn\'t supported');
    }

    /**
     * Method's implementation is required for Mailable to be sent according to Laravel docs
     *
     * @see \Illuminate\Mail\Mailable::send(), https://laravel.com/docs/7.x/mail#writing-mailables
     */
    public function build(): self
    {
        return $this;
    }

    /**
     * Check whether Mailable can be disabled
     */
    public static function canDisable(): bool
    {
        return static::$canDisable;
    }

    /**
     * These data keys are reserved and shouldn't be used as email template variable names
     *
     * @return string[]
     */
    public static function getReservedDataKeys(): array
    {
        return [
            self::DATA_KEY_MESSAGE,
            self::DATA_KEY_CONTEXT,
        ];
    }

    /**
     * Get Variable class instances associated with the Mailable
     *
     * @return Variable[]
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    /**
     * Add a footer to the email
     *
     * A mailable may override this method to add a footer to the end of the email body before it is sent.
     * Use this to add an unsubscribe link or append other automated messages.
     */
    protected function addFooter(string $locale): self
    {
        return $this;
    }

    /**
     * Allow data to be passed to the subject
     *
     * @param \Illuminate\Mail\Message $message
     *
     * @throws Exception
     */
    protected function buildSubject($message): self
    {
        $this->subject ??= ''; // Allow email with empty subject if not set
        $withoutTagViewData = collect($this->viewData)
            ->map(fn(mixed $viewableData) => is_string($viewableData) ? strip_tags($viewableData) : $viewableData)
            ->toArray();

        $subject = app('mailer')->compileParams($this->subject, $withoutTagViewData);
        // decode HTML entities for the subject
        $subject = htmlspecialchars_decode($subject, ENT_QUOTES | ENT_HTML5);

        if (empty($subject)) {
            trigger_error(
                'You are sending ' . static::getName() . ' email with empty subject',
                E_USER_WARNING
            );
        }
        $message->subject($subject);

        return $this;
    }

    /**
     * Returns variables map associated with a specific object,
     * variables names should be unique
     *
     * @return string[]
     */
    protected static function templateVariablesMap(): array
    {
        return
            [
                Context::class => ContextEmailVariable::class,
                Decision::class => DecisionEmailVariable::class,
                PKPSubmission::class => SubmissionEmailVariable::class,
                ReviewAssignment::class => ReviewAssignmentEmailVariable::class,
                QueuedPayment::class => QueuedPaymentEmailVariable::class,
                Site::class => SiteEmailVariable::class,
            ];
    }

    /**
     * Scans arguments to retrieve variables which can be assigned to the template of the email
     */
    protected function setupVariables(array $variables): void
    {
        $map = static::templateVariablesMap();
        foreach ($variables as $variable) {
            // Pass context as additional data if exists, see pkp/pkp-lib#8204
            if (is_a($variable, Context::class)) {
                static::buildViewDataUsing(function () use ($variable) {
                    return [self::DATA_KEY_CONTEXT => $variable];
                });
            }

            // Setup variables
            foreach ($map as $className => $assoc) {
                if (is_a($variable, $className)) {
                    $this->variables[] = new $assoc($variable, $this);
                    continue 2;
                }
            }

            $type = is_object($variable) ? get_class($variable) : gettype($variable);
            throw new InvalidArgumentException($type . ' argument passed to the ' . static::class . ' constructor isn\'t associated with template variables');
        }
    }

    /**
     * Get an array of data variables supported by this mailable
     * with a description of each variable.
     *
     * @return array ['variableName' => description]
     */
    public static function getDataDescriptions(): array
    {
        $args = static::getParamsClass(static::getConstructor());
        $map = static::templateVariablesMap();
        $descriptions = [];

        // check presence of traits in the current class and parents
        $traits = class_uses(static::class) ?: [];
        if ($parents = class_parents(static::class)) {
            foreach ($parents as $parent) {
                $parentTraits = class_uses($parent);
                if (!$parentTraits) {
                    continue;
                }

                $traits = array_merge(
                    $traits,
                    $parentTraits
                );
            }
        }

        if (!empty($traits)) {
            if (array_key_exists(Recipient::class, $traits)) {
                $descriptions = array_merge(
                    $descriptions,
                    RecipientEmailVariable::descriptions(),
                );
            }
            if (array_key_exists(Sender::class, $traits)) {
                $descriptions = array_merge(
                    $descriptions,
                    SenderEmailVariable::descriptions(),
                );
            }
        }

        foreach ($args as $arg) {
            $argTypes = static::getTypeNames($arg->getType());
            foreach ($map as $dataClass => $variableClass) {
                foreach ($argTypes as $argType) {
                    if (is_a($argType, $dataClass, true)) {
                        $descriptions = array_merge(
                            $descriptions,
                            $variableClass::descriptions()
                        );
                        // An intersection type could be passed in (e.g. Submission & PublishedSubmission), so allow the code check other maps for correctness
                        continue 2;
                    }
                }
            }
        }

        return $descriptions;
    }

    /**
     * Retrieve the list of type names that might compose a given type
     *
     * @return string[]
     */
    protected static function getTypeNames(ReflectionType $type): array
    {
        if ($type instanceof ReflectionNamedType) {
            return [$type->getName()];
        }
        $isUnion = $type instanceof ReflectionUnionType;
        if ($isUnion || $type instanceof ReflectionIntersectionType) {
            $flattenTypes = collect($type->getTypes())
                ->map(fn ($type) => static::getTypeNames($type))
                ->flatten();

            if ($isUnion) {
                $classTypes = $flattenTypes->filter(fn ($type) => class_exists($type));
                if ($classTypes->count() > 1) {
                    error_log(new Exception('The Mailable uses constructor arguments to retrieve variables, but union types with more than one "active" class is not well supported, only the first found one will be used. Create a specific mailable for each variant'));
                    // Retrieves the rest of types and only the first class one
                    $flattenTypes = $flattenTypes->diff($classTypes)->add($classTypes->first());
                }
            }
            return $flattenTypes->toArray();

        }
        throw new Exception('Unexpected subtype ' . $type::class);
    }

    /**
     * @see self::getTemplateVarsDescription
     */
    protected static function getConstructor(): ReflectionMethod
    {
        $constructor = (new ReflectionClass(static::class))->getConstructor();
        if (!$constructor) {
            throw new BadMethodCallException(static::class . ' requires constructor to be explicitly declared');
        }

        return $constructor;
    }

    /**
     * Retrieves arguments of the specified methods
     *
     * @see self::getTemplateVarsDescription
     *
     * @return ReflectionParameter[]
     */
    protected static function getParamsClass(ReflectionMethod $method): array
    {
        $params = $method->getParameters();
        if (empty($params)) {
            throw new BadMethodCallException(static::class . ' constructor declaration requires at least one argument');
        }

        foreach ($params as $param) {
            $type = $param->getType();
            if (!$type) {
                throw new BadMethodCallException(static::class . ' constructor argument $' . $param->getName() . ' should be type hinted');
            }
        }
        return $params;
    }

    /**
     * @copydoc Illuminate\Mail\Mailable::buildView()
     */
    protected function buildView()
    {
        $view = parent::buildView();
        if (!isset($this->footer)) {
            return $view;
        }

        /**
         * If it's an array with numerical keys, append footer to the first element;
         * if a string, just append to the view;
         * see: Illuminate\Mail\Mailer::parseView()
         */
        if (is_array($view) && isset($view[0])) {
            return [$view[0] . $this->footer, $view[1]];
        }

        if (is_string($view)) {
            return $view . $this->footer;
        }

        return $view; // $this->html, $this->textView or $this->markdown; see parent::buildView() for details
    }

    /**
     * Attach a temporary file
     */
    public function attachTemporaryFile(string $id, string $name, int $uploaderId): self
    {
        $temporaryFileManager = new TemporaryFileManager();
        $file = $temporaryFileManager->getFile($id, $uploaderId);
        if (!$file) {
            throw new Exception('Tried to attach temporary file ' . $id . ' that does not exist.');
        }
        $this->attach($file->getFilePath(), ['as' => $name]);
        return $this;
    }

    /**
     * Attach a submission file
     */
    public function attachSubmissionFile(int $id, string $name): self
    {
        $submissionFile = Repo::submissionFile()->get($id);
        if (!$submissionFile) {
            throw new Exception('Tried to attach submission file ' . $id . ' that does not exist.');
        }
        $file = app()->get('file')->get($submissionFile->getData('fileId'));
        $this->attach(
            Config::getVar('files', 'files_dir') . '/' . $file->path,
            [
                'as' => $name,
                'mime' => $file->mimetype,
            ]
        );
        return $this;
    }

    /**
     * Attach a library file
     */
    public function attachLibraryFile(int $id, string $name): self
    {
        /** @var LibraryFileDAO $libraryFileDao */
        $libraryFileDao = DAORegistry::getDAO('LibraryFileDAO');
        /** @var LibraryFile $file */
        $file = $libraryFileDao->getById($id);
        if (!$file) {
            throw new Exception('Tried to attach library file ' . $id . ' that does not exist.');
        }
        $this->attach($file->getFilePath(), ['as' => $name]);
        return $this;
    }

    /**
     * Removes a footer, e.g., this may be required to remove personal information like unsubscribe link for logging purposes
     */
    public function removeFooter(): void
    {
        $this->footer = '';
    }
}
