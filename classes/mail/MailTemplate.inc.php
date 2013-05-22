<?php

/**
 * @file classes/mail/MailTemplate.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
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
	/** @var $context object The context this message relates to */
	var $context;

	/** @var $includeSignature boolean whether to include the context's signature */
	var $includeSignature;

	/** @var $emailKey string Key of the email template we are using */
	var $emailKey;

	/** @var $locale string locale of this template */
	var $locale;

	/** @var $enabled boolean email template is enabled */
	var $enabled;

	/** @var $errorMessages array List of errors to display to the user */
	var $errorMessages;

	/** @var $persistAttachments array List of temporary files belonging to
	    email; these are maintained between requests and only sent to the
	    attachment handling functions in Mail.inc.php at time of send. */
	var $persistAttachments;
	var $attachmentsEnabled;

	/** @var $skip boolean If set to true, this message has been skipped
	    during the editing process by the user. */
	var $skip;

	/** @var $bccSender boolean whether or not to bcc the sender */
	var $bccSender;

	/** @var boolean Whether or not email fields are disabled */
	var $addressFieldsEnabled;

	/**
	 * Constructor.
	 * @param $emailKey string unique identifier for the template
	 * @param $locale string locale of the template
	 * @param $enableAttachments boolean optional Whether or not to enable article attachments in the template
	 * @param $includeSignature boolean optional
	 */
	function MailTemplate($emailKey = null, $locale = null, $enableAttachments = null, $context = null, $includeSignature = true) {
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

		// If enableAttachments is null, use the default value from the
		// configuration file
		if ($enableAttachments === null) {
			$enableAttachments = Config::getVar('email', 'enable_attachments')?true:false;
		}

		$user = $request->getUser();
		if ($enableAttachments && $user) {
			$this->_handleAttachments($user->getId());
		} else {
			$this->attachmentsEnabled = false;
		}

		$this->addressFieldsEnabled = true;

		if (isset($this->emailKey)) {
			$emailTemplateDao = DAORegistry::getDAO('EmailTemplateDAO');
			$emailTemplate = $emailTemplateDao->getEmailTemplate($this->emailKey, $this->locale, $context == null ? 0 : $context->getId());
		}

		$userSig = '';
		if ($user) {
			$userSig = $user->getLocalizedSignature();
			if (!empty($userSig)) $userSig = "\n" . $userSig;
		}

		if (isset($emailTemplate) && $request->getUserVar('subject')==null && $request->getUserVar('body')==null) {
			$this->setSubject($emailTemplate->getSubject());
			$this->setBody($emailTemplate->getBody() . $userSig);
			$this->enabled = $emailTemplate->getEnabled();

			if ($request->getUserVar('usePostedAddresses')) {
				$to = $request->getUserVar('to');
				if (is_array($to)) {
					$this->setRecipients($this->processAddresses ($this->getRecipients(), $to));
				}
				$cc = $request->getUserVar('cc');
				if (is_array($cc)) {
					$this->setCcs($this->processAddresses ($this->getCcs(), $cc));
				}
				$bcc = $request->getUserVar('bcc');
				if (is_array($bcc)) {
					$this->setBccs($this->processAddresses ($this->getBccs(), $bcc));
				}
			}
		} else {
			$this->setSubject($request->getUserVar('subject'));
			$body = $request->getUserVar('body');
			if (empty($body)) $this->setBody($userSig);
			else $this->setBody($body);
			$this->skip = (($tmp = $request->getUserVar('send')) && is_array($tmp) && isset($tmp['skip']));
			$this->enabled = true;

			if (is_array($toEmails = $request->getUserVar('to'))) {
				$this->setRecipients($this->processAddresses ($this->getRecipients(), $toEmails));
			}
			if (is_array($ccEmails = $request->getUserVar('cc'))) {
				$this->setCcs($this->processAddresses ($this->getCcs(), $ccEmails));
			}
			if (is_array($bccEmails = $request->getUserVar('bcc'))) {
				$this->setBccs($this->processAddresses ($this->getBccs(), $bccEmails));
			}
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

		if ($context && !$request->getUserVar('continued')) {
			$this->setSubject('[' . $context->getLocalizedSetting('initials') . '] ' . $this->getSubject());
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
	 * Displays an edit form to customize the email.
	 * @param $formActionUrl string
	 * @param $hiddenFormParams array
	 * @return void
	 */
	function displayEditForm($formActionUrl, $hiddenFormParams = null, $alternateTemplate = null, $additionalParameters = array()) {
		import('lib.pkp.classes.form.Form');
		$form = new Form($alternateTemplate!=null?$alternateTemplate:'email/email.tpl');

		$form->setData('formActionUrl', $formActionUrl);
		$form->setData('subject', $this->getSubject());
		$form->setData('body', $this->getBody());

		$form->setData('to', $this->getRecipients());
		$form->setData('cc', $this->getCcs());
		$form->setData('bcc', $this->getBccs());
		$form->setData('blankTo', Request::getUserVar('blankTo'));
		$form->setData('blankCc', Request::getUserVar('blankCc'));
		$form->setData('blankBcc', Request::getUserVar('blankBcc'));
		$form->setData('from', $this->getFromString(false));

		$form->setData('addressFieldsEnabled', $this->getAddressFieldsEnabled());

		$user = Request::getUser();
		if ($user) {
			$form->setData('senderEmail', $user->getEmail());
			$form->setData('bccSender', $this->bccSender);
		}

		if ($this->attachmentsEnabled) {
			$form->setData('attachmentsEnabled', true);
			$form->setData('persistAttachments', $this->persistAttachments);
		}

		$form->setData('errorMessages', $this->errorMessages);

		if ($hiddenFormParams != null) {
			$form->setData('hiddenFormParams', $hiddenFormParams);
		}

		foreach ($additionalParameters as $key => $value) {
			$form->setData($key, $value);
		}

		$form->display();
	}

	/**
	 * Send the email.
	 * Aside from calling the parent method, this actually attaches
	 * the persistent attachments if they are used.
	 * @param $clearAttachments boolean Whether to delete attachments after
	 */
	function send($clearAttachments = true) {
		if (isset($this->context)) {
			//If {$templateSignature} and/or {$templateHeader}
			// exist in the body of the message, replace them with
			// the signature; otherwise just pre/append
			// them. This is here to accomodate MIME-encoded
			// messages or other cases where the signature cannot
			// just be appended.
			$header = $this->context->getSetting('emailHeader');
			if (strstr($this->getBody(), '{$templateHeader}') === false) {
				$this->setBody($header . "\n" . $this->getBody());
			} else {
				$this->setBody(str_replace('{$templateHeader}', $header, $this->getBody()));
			}

			$signature = $this->context->getSetting('emailSignature');
			if (strstr($this->getBody(), '{$templateSignature}') === false) {
				$this->setBody($this->getBody() . "\n" . $signature);
			} else {
				$this->setBody(str_replace('{$templateSignature}', $signature, $this->getBody()));
			}

			$envelopeSender = $this->context->getSetting('envelopeSender');
			if (!empty($envelopeSender) && Config::getVar('email', 'allow_envelope_sender')) $this->setEnvelopeSender($envelopeSender);
		}
		if ($this->attachmentsEnabled) {
			foreach ($this->persistAttachments as $persistentAttachment) {
				$this->addAttachment(
					$persistentAttachment->getFilePath(),
					$persistentAttachment->getOriginalFileName(),
					$persistentAttachment->getFileType()
				);
			}
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

		if ($clearAttachments && $this->attachmentsEnabled) {
			$this->_clearAttachments($user->getId());
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

	/**
	 * Adds a persistent attachment to the current list.
	 * Persistent attachments MUST be previously initialized
	 * with handleAttachments.
	 */
	function addPersistAttachment($temporaryFile) {
		$this->persistAttachments[] = $temporaryFile;
	}

	/**
	 * Handles attachments in a generalized manner in situations where
	 * an email message must span several requests. Called from the
	 * constructor when attachments are enabled.
	 */
	function _handleAttachments($userId) {
		import('lib.pkp.classes.file.TemporaryFileManager');
		$temporaryFileManager = new TemporaryFileManager();

		$this->attachmentsEnabled = true;
		$this->persistAttachments = array();

		$deleteAttachment = Request::getUserVar('deleteAttachment');

		if (Request::getUserVar('persistAttachments') != null) foreach (Request::getUserVar('persistAttachments') as $fileId) {
			$temporaryFile = $temporaryFileManager->getFile($fileId, $userId);
			if (!empty($temporaryFile)) {
				if ($deleteAttachment != $temporaryFile->getId()) {
					$this->persistAttachments[] = $temporaryFile;
				} else {
					// This file is being deleted.
					$temporaryFileManager->deleteFile($temporaryFile->getId(), $userId);
				}
			}
		}

		if (Request::getUserVar('addAttachment') && $temporaryFileManager->uploadedFileExists('newAttachment')) {
			$user = Request::getUser();

			$this->persistAttachments[] = $temporaryFileManager->handleUpload('newAttachment', $user->getId());
		}
	}

	function getAttachmentFiles() {
		if ($this->attachmentsEnabled) return $this->persistAttachments;
		return array();
	}

	/**
	 * Delete all attachments associated with this message.
	 * Called from send().
	 * @param $userId int
	 */
	function _clearAttachments($userId) {
		import('lib.pkp.classes.file.TemporaryFileManager');
		$temporaryFileManager = new TemporaryFileManager();

		$persistAttachments = Request::getUserVar('persistAttachments');
		if (is_array($persistAttachments)) foreach ($persistAttachments as $fileId) {
			$temporaryFile = $temporaryFileManager->getFile($fileId, $userId);
			if (!empty($temporaryFile)) {
				$temporaryFileManager->deleteFile($temporaryFile->getId(), $userId);
			}
		}
	}
}

?>
