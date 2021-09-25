<?php

/**
 * @file classes/mail/MailTemplate.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MailTemplate
 * @ingroup mail
 *
 * @brief Subclass of Mail for mailing a template email.
 */

namespace PKP\mail;

use APP\core\Application;

use PKP\facades\Locale;
use APP\mail\variables\ContextEmailVariable;
use PKP\core\PKPApplication;
use PKP\facades\Repo;

class MailTemplate extends Mail
{
    public const MAIL_ERROR_INVALID_EMAIL = 1;

    /** @var object The context this message relates to */
    public $context;

    /** @var bool whether to include the context's signature */
    public $includeSignature;

    /** @var string Key of the email template we are using */
    public $emailKey;

    /** @var string locale of this template */
    public $locale;

    /** @var bool email template is enabled */
    public $enabled;

    /** @var array List of errors to display to the user */
    public $errorMessages;

    /** @var bool whether or not to bcc the sender */
    public $bccSender;

    /** @var bool Whether or not email fields are disabled */
    public $addressFieldsEnabled;

    /** @var array The list of parameters to be assigned to the template. */
    public $params;

    /**
     * Constructor.
     *
     * @param string $emailKey unique identifier for the template
     * @param string $locale locale of the template
     * @param bool $includeSignature optional
     * @param null|mixed $context
     */
    public function __construct($emailKey = null, $locale = null, $context = null, $includeSignature = true)
    {
        parent::__construct();
        $this->emailKey = $emailKey ?? null;

        // If a context wasn't specified, use the current request.
        $request = Application::get()->getRequest();
        if ($context === null) {
            $context = $request->getContext();
        }

        $this->includeSignature = $includeSignature;
        // Use current user's locale if none specified
        $this->locale = $locale ?? Locale::getLocale();

        // Record whether or not to BCC the sender when sending message
        $this->bccSender = $request->getUserVar('bccSender');

        $this->addressFieldsEnabled = true;

        if (isset($this->emailKey)) {
            $emailTemplate = Repo::emailTemplate()->getByKey($context ? $context->getId() : \PKP\core\PKPApplication::CONTEXT_SITE, $this->emailKey);
        }

        $userSig = '';
        $user = defined('SESSION_DISABLE_INIT') ? null : $request->getUser();
        if ($user && $this->includeSignature) {
            $userSig = $user->getLocalizedSignature();
            if (!empty($userSig)) {
                $userSig = '<br/>' . $userSig;
            }
        }

        if (isset($emailTemplate)) {
            $this->setSubject($emailTemplate->getData('subject', $this->locale));
            $this->setBody($emailTemplate->getData('body', $this->locale) . $userSig);
            $this->enabled = $emailTemplate->getData('enabled');
        } else {
            $this->setBody($userSig);
            $this->enabled = true;
        }

        // Default "From" to user if available, otherwise site/context principal contact
        if ($user) {
            $this->setFrom($user->getEmail(), $user->getFullName());
        } elseif (is_null($context) || is_null($context->getData('contactEmail'))) {
            $site = $request->getSite();
            $this->setFrom($site->getLocalizedContactEmail(), $site->getLocalizedContactName());
        } else {
            $this->setFrom($context->getData('contactEmail'), $context->getData('contactName'));
        }

        if ($context) {
            $this->setSubject('[' . $context->getLocalizedAcronym() . '] ' . $this->getSubject());
        }

        $this->context = $context;
        $this->params = [];
    }

    /**
     * Disable or enable the address fields on the email form.
     * NOTE: This affects the displayed form ONLY; if disabling the address
     * fields, callers should manually clearAllRecipients and add/set
     * recipients just prior to sending.
     *
     * @param bool $addressFieldsEnabled
     */
    public function setAddressFieldsEnabled($addressFieldsEnabled)
    {
        $this->addressFieldsEnabled = $addressFieldsEnabled;
    }

    /**
     * Get the enabled/disabled state of address fields on the email form.
     *
     * @return bool
     */
    public function getAddressFieldsEnabled()
    {
        return $this->addressFieldsEnabled;
    }

    /**
     * Check whether or not there were errors in the user input for this form.
     *
     * @return bool true iff one or more error messages are stored.
     */
    public function hasErrors()
    {
        return ($this->errorMessages != null);
    }

    /**
     * Assigns values to e-mail parameters.
     *
     * @param array $params Associative array of variables to supply to the email template
     */
    public function assignParams($params = [])
    {
        $application = Application::get();
        $request = $application->getRequest();
        $site = $request->getSite();

        if ($this->context) {
            // Add context-specific variables
            $dispatcher = $application->getDispatcher();
            $params = array_merge([
                'signature' => $this->context->getData('contactName'),
                ContextEmailVariable::CONTEXT_NAME => $this->context->getLocalizedName(),
                ContextEmailVariable::CONTEXT_URL => $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $this->context->getPath()),
                'mailingAddress' => htmlspecialchars(nl2br($this->context->getData('mailingAddress'))),
                'contactEmail' => htmlspecialchars($this->context->getData('contactEmail')),
                'contactName' => htmlspecialchars($this->context->getData('contactName')),
            ], $params);
        } else {
            // No context available
            $params = array_merge([
                'signature' => $site->getLocalizedContactName(),
            ], $params);
        }

        if (!defined('SESSION_DISABLE_INIT') && ($user = $request->getUser())) {
            // Add user-specific variables
            $params = array_merge([
                'senderEmail' => $user->getEmail(),
                'senderName' => $user->getFullName(),
            ], $params);
        }

        // Add some general variables
        $params = array_merge([
            'siteTitle' => $site->getLocalizedTitle(),
        ], $params);

        $this->params = $params;
    }

    /**
     * Returns true if the email template is enabled; false otherwise.
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * Send the email.
     *
     * @return bool false if there was a problem sending the email
     */
    public function send()
    {
        if (isset($this->context)) {
            $signature = $this->context->getData('emailSignature');
            if (strstr($this->getBody(), '{$templateSignature}') === false) {
                $this->setBody($this->getBody() . '<br/>' . $signature);
            } else {
                $this->setBody(str_replace('{$templateSignature}', $signature, $this->getBody()));
            }

            $envelopeSender = $this->context->getData('envelopeSender');
            if (!empty($envelopeSender) && Config::getVar('email', 'allow_envelope_sender')) {
                $this->setEnvelopeSender($envelopeSender);
            }
        }

        $request = Application::get()->getRequest();
        $user = defined('SESSION_DISABLE_INIT') ? null : $request->getUser();

        if ($user && $this->bccSender) {
            $this->addBcc($user->getEmail(), $user->getFullName());
        }

        // Replace variables in message with values
        $this->replaceParams();

        return parent::send();
    }

    /**
     * Replace template variables in the message body.
     */
    public function replaceParams()
    {
        $subject = $this->getSubject();
        $body = $this->getBody();
        foreach ($this->params as $key => $value) {
            if (!is_object($value)) {
                // $value is checked to identify URL pattern
                if (filter_var($value, FILTER_VALIDATE_URL) != false) {
                    $body = $this->manageURLValues($body, $key, $value);
                } else {
                    $body = str_replace('{$' . $key . '}', $value, $body);
                }
            }

            $subject = str_replace('{$' . $key . '}', $value, $subject);
        }
        $this->setSubject($subject);
        $this->setBody($body);
    }

    /**
     * Assigns user-specific values to email parameters, sends
     * the email, then clears those values.
     *
     * @param array $params Associative array of variables to supply to the email template
     *
     * @return bool false if there was a problem sending the email
     */
    public function sendWithParams($params)
    {
        $savedHeaders = $this->getHeaders();
        $savedSubject = $this->getSubject();
        $savedBody = $this->getBody();

        $this->assignParams($params);
        $ret = $this->send();

        $this->setHeaders($savedHeaders);
        $this->setSubject($savedSubject);
        $this->setBody($savedBody);

        return $ret;
    }

    /**
     * Clears the recipient, cc, and bcc lists.
     *
     * @param bool $clearHeaders if true, also clear headers
     */
    public function clearRecipients($clearHeaders = true)
    {
        $this->setData('recipients', null);
        $this->setData('ccs', null);
        $this->setData('bccs', null);
        if ($clearHeaders) {
            $this->setData('headers', null);
        }
    }

    /**
     * Finds and changes appropriately URL valued template parameter keys.
     *
     * @param string $targetString The string that contains the original {$key}s template variables
     * @param string $key The key we are looking for, and has an URL as its $value
     * @param string $value The value of the $key
     *
     * @return string the $targetString replaced appropriately
     */
    public function manageURLValues($targetString, $key, $value)
    {
        // If the value is URL, we need to find if $key resides in a href={$...} pattern.
        preg_match_all('/=[\\\'"]{\\$' . preg_quote($key) . '}/', $targetString, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        // if we find some ={$...} occurences of the $key in the email body, then we need to replace them with
        // the corresponding value
        if ($matches) {
            // We make the change backwords (last offset replaced first) so that smaller offsets correctly mark the string they supposed to.
            for ($i = count($matches) - 1; $i >= 0; $i--) {
                $match = $matches[$i][0];
                $targetString = substr_replace($targetString, str_replace('{$' . $key . '}', $value, $match[0]), $match[1], strlen($match[0]));
            }
        }

        // all the ={$...} patterns have been replaced - now we can change the remaining URL $keys with the following pattern
        $value = "<a href='${value}' class='${key}-style-class'>${value}</a>";

        $targetString = str_replace('{$' . $key . '}', $value, $targetString);

        return $targetString;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\mail\MailTemplate', '\MailTemplate');
    define('MAIL_ERROR_INVALID_EMAIL', \MailTemplate::MAIL_ERROR_INVALID_EMAIL);
}
