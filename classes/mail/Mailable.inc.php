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
use PKP\mail\mailables\Recipient;
use PKP\mail\mailables\Sender;
use APP\mail\variables\ContextEmailVariable;
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
     * One or more groups this mailable should be included in
     *
     * Mailables are assigned to one or more groups so that they
     * can be organized when shown in the UI.
     */
    protected static array $groupIds = [self::GROUP_OTHER];

    public function __construct(array $args)
    {
        $this->setData($args);
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
     * Adds variables to be compiled with email template
     */
    public function addVariables(array $variables) : self
    {
        $this->viewData = array_merge($this->viewData, $variables);
        return $this;
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
    protected function setData(array $args) : void
    {
        $map = static::templateVariablesMap();

        foreach ($args as $arg) {
            foreach ($map as $className => $assoc) {
                if (is_a($arg, $className)) {
                    $assocVariable = new $assoc($arg);
                    $this->viewData = array_merge(
                        $this->viewData,
                        $assocVariable->getValue()
                    );
                    continue 2;
                }
            }

            // Give up, object isn't mapped
            $type = is_object($arg) ? get_class($arg) : gettype($arg);
            throw new InvalidArgumentException($type . ' argument passed to the ' . static::class . ' constructor isn\'t associated with template variables');
        }
    }

    /**
     * Retrieves array of variables that can be assigned to email templates
     * @return array ['variableName' => description]
     */
    public static function getVariables() : array
    {
        $args = static::getParamsClass(static::getConstructor());
        $map = static::templateVariablesMap();
        $variables = [];

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
                $variables = array_merge(
                    $variables,
                    RecipientEmailVariable::getDescription(),
                );
            }
            if (array_key_exists(Sender::class, $traits)) {
                $variables = array_merge(
                    $variables,
                    SenderEmailVariable::getDescription(),
                );
            }
        }

        foreach ($args as $arg) { /** @var  ReflectionParameter $arg) */
            $class = $arg->getType()->getName();

            if (!array_key_exists($class, $map)) {
                continue;
            }

            // No special treatment for others
            $variables = array_merge(
                $variables,
                $map[$class]::getDescription()
            );
        }

        return $variables;
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
