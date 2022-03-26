<?php

/**
 * @file classes/mail/Mailable.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Mailable
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

use APP\core\Services;
use APP\decision\Decision;
use APP\facades\Repo;
use APP\mail\variables\ContextEmailVariable;
use BadMethodCallException;
use Exception;
use Illuminate\Mail\Mailable as IlluminateMailable;
use InvalidArgumentException;
use PKP\config\Config;
use PKP\context\Context;
use PKP\facades\Locale;
use PKP\context\LibraryFile;
use PKP\context\LibraryFileDAO;
use PKP\db\DAORegistry;
use PKP\file\TemporaryFileManager;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;
use PKP\mail\variables\DecisionEmailVariable;
use PKP\mail\variables\QueuedPaymentEmailVariable;
use PKP\mail\variables\RecipientEmailVariable;
use PKP\mail\variables\ReviewAssignmentEmailVariable;
use PKP\mail\variables\SenderEmailVariable;
use PKP\mail\variables\SiteEmailVariable;
use PKP\mail\variables\SubmissionEmailVariable;
use PKP\mail\variables\Variable;
use PKP\payment\QueuedPayment;
use PKP\site\Site;
use PKP\submission\PKPSubmission;
use PKP\submission\reviewAssignment\ReviewAssignment;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;

class Mailable extends IlluminateMailable
{
    /** Used internally by Illuminate Mailer. Do not touch. */
    public const DATA_KEY_MESSAGE = 'message';

    public const GROUP_OTHER = 'other';
    public const GROUP_SUBMISSION = 'submission';
    public const GROUP_REVIEW = 'review';
    public const GROUP_COPYEDITING = 'copyediting';
    public const GROUP_PRODUCTION = 'production';

    public const ATTACHMENT_TEMPORARY_FILE = 'temporaryFileId';
    public const ATTACHMENT_SUBMISSION_FILE = 'submissionFileId';
    public const ATTACHMENT_LIBRARY_FILE = 'libraryFileId';

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
    public function getSupportsTemplates(): bool
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
     * Add data for this email
     */
    public function addData(array $data): self
    {
        $this->viewData = array_merge($this->viewData, $data);
        return $this;
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
    public function setData(?string $locale = null)
    {
        if (is_null($locale)) {
            $locale = Locale::getLocale();
        }
        foreach ($this->variables as $variable) {
            $this->viewData = array_merge(
                $this->viewData,
                $variable->values($locale)
            );
        }
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
    public function body(string $view): self
    {
        return parent::view($view, []);
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
     * Check whether the subject and body of the email can be edited in the Mailable settings
     */
    public static function canEdit(): bool
    {
        return static::$canEdit;
    }

    /**
     * Check whether Mailable can be disabled
     */
    public static function canDisable(): bool
    {
        return static::$canDisable;
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
     * Get associated stage IDs
     */
    public static function getStageIds(): array
    {
        return static::$stageIds;
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
        if (!$this->subject) {
            throw new Exception('Subject isn\'t specified in ' . static::class);
        }

        $subject = app('mailer')->compileParams($this->subject, $this->viewData);
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
            foreach ($map as $className => $assoc) {
                if (is_a($variable, $className)) {
                    $this->variables[] = new $assoc($variable);
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

        foreach ($args as $arg) { /** @var ReflectionParameter $arg) */
            $class = $arg->getType()->getName();

            if (!array_key_exists($class, $map)) {
                continue;
            }

            // No special treatment for others
            $descriptions = array_merge(
                $descriptions,
                $map[$class]::descriptions()
            );
        }

        return $descriptions;
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
        $file = Services::get('file')->get($submissionFile->getData('fileId'));
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
}
