<?php

/**
 * @file classes/notification/form/NotificationMailingListForm.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NotificationMailingListForm
 * @ingroup notification_form
 *
 * @brief Form to subscribe to the notification mailing list
 */


import('lib.pkp.classes.form.Form');
import('classes.notification.Notification');

class NotificationMailingListForm extends Form {

	/** @var boolean Whether or not Captcha support is enabled */
	var $captchaEnabled;

	/**
	 * Constructor.
	 */
	function NotificationMailingListForm() {
		parent::Form('notification/maillist.tpl');

		$this->captchaEnabled = Config::getVar('captcha', 'captcha_on_mailinglist') && Config::getVar('captcha', 'recaptcha');

		// Validation checks for this form
		if ($this->captchaEnabled) {
			$this->addCheck(new FormValidatorReCaptcha($this, 'recaptcha_challenge_field', 'recaptcha_response_field', Request::getRemoteAddr(), 'common.captchaField.badCaptcha'));
		}
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorEmail($this, 'email', 'required', 'notification.mailList.emailInvalid'));
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$userVars = array('email');
		
		if ($this->captchaEnabled) {
			$userVars[] = 'recaptcha_challenge_field';
			$userVars[] = 'recaptcha_response_field';
		}
		
		$this->readUserVars($userVars);
	}

	/**
	 * Display the form.
	 * @param $request PKPRequest
	 */
	function display($request) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('new', true);
		
		if ($this->captchaEnabled) {
			import('lib.pkp.lib.recaptcha.recaptchalib');
			$publicKey = Config::getVar('captcha', 'recaptcha_public_key');
			$useSSL = Config::getVar('security', 'force_ssl')?true:false;
			$reCaptchaHtml = recaptcha_get_html($publicKey, null, $useSSL);
			$templateMgr->assign('reCaptchaHtml', $reCaptchaHtml);
			$templateMgr->assign('captchaEnabled', true);
		}

		return parent::display();
	}

	/**
	 * Save the form
	 * @param $request PKPRequest
	 */
	function execute($request) {
		$userEmail = $this->getData('email');
		$context = $request->getContext();

		$notificationMailListDao = DAORegistry::getDAO('NotificationMailListDAO');
		if($password = $notificationMailListDao->subscribeGuest($userEmail, $context->getId())) {
			$notificationManager = new NotificationManager();
			$notificationManager->sendMailingListEmail($request, $userEmail, $password, 'NOTIFICATION_MAILLIST_WELCOME');
			return true;
		} else {
			$request->redirect(null, 'notification', 'mailListSubscribed', array('error'));
			return false;
		}
	}
}

?>
