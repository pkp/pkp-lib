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

use BadMethodCallException;
use Exception;
use Illuminate\Mail\Mailable as IlluminateMailable;
use InvalidArgumentException;
use PKP\context\Context;
use APP\mail\variables\ContextEmailVariable;
use PKP\facades\Locale;
use PKP\mail\variables\QueuedPaymentEmailVariable;
use PKP\mail\variables\RecipientEmailVariable;
use PKP\mail\variables\ReviewAssignmentEmailVariable;
use PKP\mail\variables\SenderEmailVariable;
use PKP\mail\variables\SiteEmailVariable;
use PKP\mail\variables\StageAssignmentEmailVariable;
use PKP\mail\variables\SubmissionEmailVariable;
use PKP\payment\QueuedPayment;
use PKP\site\Site;
use PKP\stageAssignment\StageAssignment;
use PKP\submission\PKPSubmission;
use PKP\submission\reviewAssignment\ReviewAssignment;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;

class Mailable extends IlluminateMailable
{

    /**
     * Name of the variable representing a message object assigned to email templates by Illuminate Mailer by default
     * @var string
     */
    public const DATA_KEY_MESSAGE = 'message';

    public const GROUP_OTHER = 'other';
    public const GROUP_SUBMISSION = 'submission';
    public const GROUP_REVIEW = 'review';
    public const GROUP_COPYEDITING = 'copyediting';
    public const GROUP_PRODUCTION = 'production';

    /**
     * The email variables handled by this mailable
     *
     * @param array<Variable>
     */
    protected array $variables = [];

    /**
     * One or more groups this mailable should be included in
     *
     * Mailables are assigned to one or more groups so that they
     * can be organized when shown in the UI.
     */
    protected static array $groupIds = [self::GROUP_OTHER];

    // Locale key, name of the Mailable displayed in the UI
    protected static ?string $name = null;

    // Locale key, description of the Mailable displayed in the UI
    protected static ?string $description = null;

    // Whether Mailable supports additional templates, besides the default
    protected static bool $supportsTemplates = false;

    public function __construct(array $variables = [])
    {
        if (!empty($variables)) {
            $this->setupVariables($variables);
        }
    }

    /**
     * Add data for this email
     */
    public function addData(array $data) : self
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
     * @throws BadMethodCallException
     */
    public function locale($localeKey)
    {
        throw new BadMethodCallException('This method isn\'t supported, data passed to ' . static::class .
            ' should already by localized.');
    }

    /**
     * Use instead of the \Illuminate\Mail\Mailable::view() to compile template message's body
     * @param string $view HTML string with template variables
     */
    public function body(string $view) : self
    {
        return parent::view($view, []);
    }

    /**
     * Doesn't support Illuminate markdown
     * @throws BadMethodCallException
     */
    public function markdown($view, array $data = []) : self
    {
        throw new BadMethodCallException('Markdown isn\'t supported');
    }

    /**
     * @return array [self::GROUP_...] workflow stages associated with a mailable
     */
    public static function getGroupIds() : array
    {
        return static::$groupIds;
    }

    /**
     * Method's implementation is required for Mailable to be sent according to Laravel docs
     * @see \Illuminate\Mail\Mailable::send(), https://laravel.com/docs/7.x/mail#writing-mailables
     */
    public function build() : self
    {
        return $this;
    }

    /**
     * Allow data to be passed to the subject
     * @param \Illuminate\Mail\Message $message
     * @throws Exception
     */
    protected function buildSubject($message) : self
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
     * @return string[]
     */
    protected static function templateVariablesMap() : array
    {
        return
            [
                Site::class => SiteEmailVariable::class,
                Context::class => ContextEmailVariable::class,
                PKPSubmission::class => SubmissionEmailVariable::class,
                ReviewAssignment::class => ReviewAssignmentEmailVariable::class,
                StageAssignment::class => StageAssignmentEmailVariable::class,
                QueuedPayment::class => QueuedPaymentEmailVariable::class,
            ];
    }

    /**
     * Scans arguments to retrieve variables which can be assigned to the template of the email
     */
    protected function setupVariables(array $variables) : void
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
    public static function getDataDescriptions() : array
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
                    RecipientEmailVariable::getDescription(),
                );
            }
            if (array_key_exists(Sender::class, $traits)) {
                $descriptions = array_merge(
                    $descriptions,
                    SenderEmailVariable::getDescription(),
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
                $map[$class]::getDescription()
            );
        }

        return $descriptions;
    }

    /**
     * @see self::getTemplateVarsDescription
     */
    protected static function getConstructor() : ReflectionMethod
    {
        $constructor = (new ReflectionClass(static::class))->getConstructor();
        if (!$constructor) {
            throw new BadMethodCallException(static::class . ' requires constructor to be explicitly declared');
        }

        return $constructor;
    }

    /**
     * Retrieves arguments of the specified methods
     * @see self::getTemplateVarsDescription
     */
    protected static function getParamsClass(ReflectionMethod $method) : array
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
}
