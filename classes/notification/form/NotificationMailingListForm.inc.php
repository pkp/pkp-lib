<?php
/**
 * @defgroup notification_form
 */

/**
 * @file classes/notification/form/NotificationMailingListForm.inc.php
 *
 * Copyright (c) 2013-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
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
	/** @var boolean Whether or not Recaptcha support is enabled */
	var $recaptchaEnabled;
	
	/**
	 * Constructor.
	 */
	function NotificationMailingListForm() {
		parent::Form('notification/maillist.tpl');

		import('lib.pkp.classes.captcha.CaptchaManager');
		import('lib.pkp.lib.recaptcha.recaptchalib');
		$captchaManager = new CaptchaManager();
		$this->captchaEnabled = ($captchaManager->isEnabled() && Config::getVar('captcha', 'captcha_on_mailinglist'))?true:false;
		$this->recaptchaEnabled = Config::getVar('captcha', 'captcha_on_mailinglist') && Config::getVar('captcha', 'recaptcha');

		// Validation checks for this form
		if ($this->captchaEnabled && $this->recaptchaEnabled) {
			if (Config::getVar('captcha', 'recaptcha_enforce_hostname')) {
				$host = Request::getServerHost();
			} else { 
				$host = '';
			}
			$reCaptchaVersion = intval(Config::getVar('captcha', 'recaptcha_version', RECAPTCHA_VERSION_LEGACY));
			$this->addCheck(new FormValidatorReCaptcha($this, 'recaptcha_challenge_field', ($reCaptchaVersion === RECAPTCHA_VERSION_LEGACY ? 'recaptcha_response_field' : 'g-recaptcha-response'), Request::getRemoteAddr(), 'common.captchaField.badCaptcha', $host));
		} elseif ($this->captchaEnabled) {
			$this->addCheck(new FormValidatorCaptcha($this, 'captcha', 'captchaId', 'common.captchaField.badCaptcha'));
		}
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorEmail($this, 'email', 'required', 'notification.mailList.emailInvalid'));
		$this->addCheck(new FormValidatorCustom($this, 'email', 'required', 'user.register.form.emailsDoNotMatch', create_function('$email,$form', 'return $email == $form->getData(\'confirmEmail\');'), array(&$this)));
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$userVars = array('email', 'confirmEmail');
		
		if ($this->captchaEnabled) {
			if ($this->recaptchaEnabled) {
				$reCaptchaVersion = intval(Config::getVar('captcha', 'recaptcha_version', RECAPTCHA_VERSION_LEGACY));
				if ($reCaptchaVersion === RECAPTCHA_VERSION_LEGACY) {
					$userVars[] = 'recaptcha_challenge_field';
					$userVars[] = 'recaptcha_response_field';
				} else {
					$userVars[] = 'g-recaptcha-response';
				}
			} else {
				$userVars[] = 'captchaId';
				$userVars[] = 'captcha';
			}
		}
		
		$this->readUserVars($userVars);
	}

	/**
	 * Display the form.
	 */
	function display(&$request) {
		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign('new', true);
		
		if ($this->captchaEnabled && $this->recaptchaEnabled) {
			import('lib.pkp.lib.recaptcha.recaptchalib');
			$reCaptchaVersion = intval(Config::getVar('captcha', 'recaptcha_version', RECAPTCHA_VERSION_LEGACY));
			$publicKey = Config::getVar('captcha', 'recaptcha_public_key');
			$useSSL = Config::getVar('security', 'force_ssl')||Request::getProtocol()=='https'?true:false;
			$reCaptchaHtml = recaptcha_versioned_get_html($reCaptchaVersion, $publicKey, null, $useSSL);
			$templateMgr->assign('reCaptchaHtml', $reCaptchaHtml);
			$templateMgr->assign('captchaEnabled', true);
		} elseif ($this->captchaEnabled) {
			import('lib.pkp.classes.captcha.CaptchaManager');
			$captchaManager = new CaptchaManager();
			$captcha =& $captchaManager->createCaptcha();
			if ($captcha) {
				$templateMgr->assign('captchaEnabled', $this->captchaEnabled);
				$this->setData('captchaId', $captcha->getId());
			}
		}

		$context =& $request->getContext();
		if ($context) {
			$templateMgr->assign('allowRegReviewer', $context->getSetting('allowRegReviewer'));
			$templateMgr->assign('allowRegAuthor', $context->getSetting('allowRegAuthor'));
		}

		return parent::display();
	}

	/**
	 * Save the form
	 */
	function execute(&$request) {
		$userEmail = $this->getData('email');
		$context =& $request->getContext();

		$notificationMailListDao =& DAORegistry::getDAO('NotificationMailListDAO');
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
