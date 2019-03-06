<?php

/**
 * @file classes/mail/MailTemplate.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MailTemplate
 * @ingroup mail
 *
 * @brief Subclass of Mail for mailing a template email.
 */


import('lib.pkp.classes.mail.Mail');

define('MAIL_ERROR_INVALID_EMAIL', 0x000001);

class MailTemplate extends Mail {
	/** @var object The context this message relates to */
	var $context;

	/** @var boolean whether to include the context's signature */
	var $includeSignature;

	/** @var string Key of the email template we are using */
	var $emailKey;

	/** @var string locale of this template */
	var $locale;

	/** @var boolean email template is enabled */
	var $enabled;

	/** @var array List of errors to display to the user */
	var $errorMessages;

	/** @var boolean whether or not to bcc the sender */
	var $bccSender;

	/** @var boolean Whether or not email fields are disabled */
	var $addressFieldsEnabled;

	/** @var array The list of parameters to be assigned to the template. */
	var $params;

	/**
	 * Constructor.
	 * @param $emailKey string unique identifier for the template
	 * @param $locale string locale of the template
	 * @param $includeSignature boolean optional
	 */
	function __construct($emailKey = null, $locale = null, $context = null, $includeSignature = true) {
		parent::__construct();
		$this->emailKey = isset($emailKey) ? $emailKey : null;

		// If a context wasn't specified, use the current request.
		$request = Application::get()->getRequest();
		if ($context === null) $context = $request->getContext();

		$this->includeSignature = $includeSignature;
		// Use current user's locale if none specified
		$this->locale = isset($locale) ? $locale : AppLocale::getLocale();

		// Record whether or not to BCC the sender when sending message
		$this->bccSender = $request->getUserVar('bccSender');

		$this->addressFieldsEnabled = true;

		if (isset($this->emailKey)) {
			$emailTemplateDao = DAORegistry::getDAO('EmailTemplateDAO');
			$emailTemplate = $emailTemplateDao->getEmailTemplate($this->emailKey, $this->locale, $context == null ? 0 : $context->getId());
		}

		$userSig = '';
		$user = defined('SESSION_DISABLE_INIT')?null:$request->getUser();
		if ($user && $this->includeSignature) {
			$userSig = $user->getLocalizedSignature();
			if (!empty($userSig)) $userSig = "<br/>" . $userSig;
		}

		if (isset($emailTemplate)) {
			$this->setSubject($emailTemplate->getSubject());
			$this->setBody($emailTemplate->getBody() . $userSig);
			$this->enabled = $emailTemplate->getEnabled();
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
		$this->params = array();
	}

	/**
	 * Disable or enable the address fields on the email form.
	 * NOTE: This affects the displayed form ONLY; if disabling the address
	 * fields, callers should manually clearAllRecipients and add/set
	 * recipients just prior to sending.
	 * @param $addressFieldsEnabled boolean
	 */
	function setAddressFieldsEnabled($addressFieldsEnabled) {
		$this->addressFieldsEnabled = $addressFieldsEnabled;
	}

	/**
	 * Get the enabled/disabled state of address fields on the email form.
	 * @return boolean
	 */
	function getAddressFieldsEnabled() {
		return $this->addressFieldsEnabled;
	}

	/**
	 * Check whether or not there were errors in the user input for this form.
	 * @return boolean true iff one or more error messages are stored.
	 */
	function hasErrors() {
		return ($this->errorMessages != null);
	}

	/**
	 * Assigns values to e-mail parameters.
	 * @param $params array Associative array of variables to supply to the email template
	 */
	function assignParams($params = array()) {
		$application = Application::getApplication();
		$request = $application->getRequest();
		$site = $request->getSite();

		if ($this->context) {
			// Add context-specific variables
			$dispatcher = $application->getDispatcher();
			$params = array_merge(array(
				'principalContactSignature' => $this->context->getData('contactName'),
				'contextName' => $this->context->getLocalizedName(),
				'contextUrl' => $dispatcher->url($request, ROUTE_PAGE, $this->context->getPath()),
			), $params);
		} else {
			// No context available
			$params = array_merge(array(
				'principalContactSignature' => $site->getLocalizedContactName(),
			), $params);
		}

		if (!defined('SESSION_DISABLE_INIT') && ($user = $request->getUser())) {
			// Add user-specific variables
			$params = array_merge(array(
				'senderEmail' => $user->getEmail(),
				'senderName' => $user->getFullName(),
			), $params);
		}

		// Add some general variables
		$params = array_merge(array(
			'siteTitle' => $site->getLocalizedTitle(),
		), $params);

		$this->params = $params;
	}

	/**
	 * Returns true if the email template is enabled; false otherwise.
	 * @return boolean
	 */
	function isEnabled() {
		return $this->enabled;
	}

	/**
	 * Send the email.
	 * @return boolean false if there was a problem sending the email
	 */
	function send() {
		if (isset($this->context)) {
			$signature = $this->context->getData('emailSignature');
			if (strstr($this->getBody(), '{$templateSignature}') === false) {
				$this->setBody($this->getBody() . "<br/>" . $signature);
			} else {
				$this->setBody(str_replace('{$templateSignature}', $signature, $this->getBody()));
			}

			$envelopeSender = $this->context->getData('envelopeSender');
			if (!empty($envelopeSender) && Config::getVar('email', 'allow_envelope_sender')) $this->setEnvelopeSender($envelopeSender);
		}

		$user = defined('SESSION_DISABLE_INIT')?null:Request::getUser();

		if ($user && $this->bccSender) {
			$this->addBcc($user->getEmail(), $user->getFullName());
		}

		// Replace variables in message with values
		$this->replaceParams();

		return parent::send();
	}

	/**
	 * Replace template variables in the message body.
	 * @param $params array Parameters to assign (augments anything provided via setParams)
	 */
	function replaceParams() {
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
	 * @param $params array Associative array of variables to supply to the email template
	 * @return boolean false if there was a problem sending the email
	 */
	function sendWithParams($params) {
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
	 * @param $clearHeaders boolean if true, also clear headers
	 * @return void
	 */
	function clearRecipients($clearHeaders = true) {
		$this->setData('recipients', null);
		$this->setData('ccs', null);
		$this->setData('bccs', null);
		if ($clearHeaders) {
			$this->setData('headers', null);
		}
	}

	/**
	 * Finds and changes appropriately URL valued template parameter keys.
	 * @param $targetString string The string that contains the original {$key}s template variables
	 * @param $key string The key we are looking for, and has an URL as its $value
	 * @param $value string The value of the $key
	 * @return string the $targetString replaced appropriately
	 */
	function manageURLValues($targetString, $key, $value) {
		// If the value is URL, we need to find if $key resides in a href={$...} pattern.
		preg_match_all('/=[\\\'"]{\\$' . preg_quote($key) . '}/', $targetString, $matches, PREG_SET_ORDER|PREG_OFFSET_CAPTURE);

		// if we find some ={$...} occurences of the $key in the email body, then we need to replace them with
		// the corresponding value
		if ($matches) {
			// We make the change backwords (last offset replaced first) so that smaller offsets correctly mark the string they supposed to.
			for($i = count($matches)-1; $i >= 0; $i--) {
				$match = $matches[$i][0];
				$targetString = substr_replace($targetString,  str_replace('{$' . $key . '}', $value, $match[0]), $match[1], strlen($match[0]));
			}
		}

		// all the ={$...} patterns have been replaced - now we can change the remaining URL $keys with the following pattern
		$value = "<a href='$value' class='$key-style-class'>$value</a>";

		$targetString = str_replace('{$' . $key . '}', $value, $targetString);

		return $targetString;
	}
}


