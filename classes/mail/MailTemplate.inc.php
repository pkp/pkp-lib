<?php

/**
 * @file classes/mail/MailTemplate.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
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

	/** @var boolean If set to true, this message has been skipped
	    during the editing process by the user. */
	var $skip;

	/** @var boolean whether or not to bcc the sender */
	var $bccSender;

	/** @var boolean Whether or not email fields are disabled */
	var $addressFieldsEnabled;

	/**
	 * Constructor.
	 * @param $emailKey string unique identifier for the template
	 * @param $locale string locale of the template
	 * @param $includeSignature boolean optional
	 */
	function MailTemplate($emailKey = null, $locale = null, $context = null, $includeSignature = true) {
		parent::Mail();
		$this->emailKey = isset($emailKey) ? $emailKey : null;

		// If a context wasn't specified, use the current request.
		$application = PKPApplication::getApplication();
		$request = $application->getRequest();
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
		$user = $request->getUser();
		if ($user && $this->includeSignature) {
			$userSig = $user->getLocalizedSignature();
			if (!empty($userSig)) $userSig = "<br/>" . $userSig;
		}

		if (isset($emailTemplate)) {
			$this->setSubject($emailTemplate->getSubject());
			$this->setBody(nl2br($emailTemplate->getBody() . $userSig));
			$this->enabled = $emailTemplate->getEnabled();
		} else {
			$this->setBody($userSig);
			$this->enabled = true;
		}

		// Default "From" to user if available, otherwise site/context principal contact
		if ($user) {
			$this->setReplyTo($user->getEmail(), $user->getFullName());
		}
		if (!$context) {
			$site = $request->getSite();
			$this->setFrom($site->getLocalizedContactEmail(), $site->getLocalizedContactName());
		} else {
			$this->setFrom($context->getSetting('contactEmail'), $context->getSetting('contactName'));
		}

		if ($context) {
			$this->setSubject('[' . $context->getLocalizedAcronym() . '] ' . $this->getSubject());
		}

		$this->context = $context;
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
	 * @param $paramArray array
	 * @return void
	 */
	function assignParams($paramArray = array()) {
		$subject = $this->getSubject();
		$body = $this->getBody();

		if (isset($this->context)) {
			$paramArray['principalContactSignature'] = $this->context->getSetting('contactName');
			$paramArray['contextName'] = $this->context->getLocalizedName();
			$application = PKPApplication::getApplication();
			$request = $application->getRequest();
			$router = $request->getRouter();
			$dispatcher = $request->getDispatcher();
			if (!isset($paramArray['contextUrl'])) $paramArray['contextUrl'] = $dispatcher->url($request, ROUTE_PAGE, $router->getRequestedContextPath($request));
		} else {
			$site = Request::getSite();
			$paramArray['principalContactSignature'] = $site->getLocalizedContactName();
		}

		// Replace variables in message with values
		foreach ($paramArray as $key => $value) {
			if (!is_object($value)) {
				$subject = str_replace('{$' . $key . '}', $value, $subject);
				$body = str_replace('{$' . $key . '}', $value, $body);
			}
		}

		$this->setSubject($subject);
		$this->setBody($body);
	}

	/**
	 * Returns true if the email template is enabled; false otherwise.
	 * @return boolean
	 */
	function isEnabled() {
		return $this->enabled;
	}

	/**
	 * Processes form-submitted addresses for inclusion in
	 * the recipient list
	 * @param $currentList array Current recipient/cc/bcc list
	 * @param $newAddresses array "Raw" form parameter for additional addresses
	 */
	function &processAddresses($currentList, &$newAddresses) {
		foreach ($newAddresses as $newAddress) {
			$regs = array();
			// Match the form "My Name <my_email@my.domain.com>"
			if (String::regexp_match_get('/^([^<>' . "\n" . ']*[^<> ' . "\n" . '])[ ]*<(?P<email>' . PCRE_EMAIL_ADDRESS . ')>$/i', $newAddress, $regs)) {
				$currentList[] = array('name' => $regs[1], 'email' => $regs['email']);

			} elseif (String::regexp_match_get('/^<?(?P<email>' . PCRE_EMAIL_ADDRESS . ')>?$/i', $newAddress, $regs)) {
				$currentList[] = array('name' => '', 'email' => $regs['email']);

			} elseif ($newAddress != '') {
				$this->errorMessages[] = array('type' => MAIL_ERROR_INVALID_EMAIL, 'address' => $newAddress);
			}
		}
		return $currentList;
	}

	/**
	 * Send the email.
	 */
	function send() {
		if (isset($this->context)) {
			//If {$templateSignature} and/or {$templateHeader}
			// exist in the body of the message, replace them with
			// the signature; otherwise just pre/append
			// them. This is here to accomodate MIME-encoded
			// messages or other cases where the signature cannot
			// just be appended.
			$header = $this->context->getSetting('emailHeader');
			if (strstr($this->getBody(), '{$templateHeader}') === false) {
				$this->setBody($header . "<br/>" . $this->getBody());
			} else {
				$this->setBody(str_replace('{$templateHeader}', $header, $this->getBody()));
			}

			$signature = $this->context->getSetting('emailSignature');
			if (strstr($this->getBody(), '{$templateSignature}') === false) {
				$this->setBody($this->getBody() . "<br/>" . $signature);
			} else {
				$this->setBody(str_replace('{$templateSignature}', $signature, $this->getBody()));
			}

			$envelopeSender = $this->context->getSetting('envelopeSender');
			if (!empty($envelopeSender) && Config::getVar('email', 'allow_envelope_sender')) $this->setEnvelopeSender($envelopeSender);
		}

		$user = Request::getUser();

		if ($user && $this->bccSender) {
			$this->addBcc($user->getEmail(), $user->getFullName());
		}

		if (isset($this->skip) && $this->skip) {
			$result = true;
		} else {
			$result = parent::send();
		}

		return $result;
	}

	/**
	 * Assigns user-specific values to email parameters, sends
	 * the email, then clears those values.
	 * @param $paramArray array
	 * @return void
	 */
	function sendWithParams($paramArray) {
		$savedHeaders = $this->getHeaders();
		$savedSubject = $this->getSubject();
		$savedBody = $this->getBody();

		$this->assignParams($paramArray);

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
}

?>
