<?php
/**
 * @defgroup user_form User Forms
 */

/**
 * @file classes/user/form/RegistrationForm.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RegistrationForm
 * @ingroup user_form
 *
 * @brief Form for user registration.
 */

import('lib.pkp.classes.form.Form');

class RegistrationForm extends Form {

	/** @var User The user object being created (available to hooks during registrationform::execute hook) */
	var $user;

	/** @var boolean user is already registered with another context */
	var $existingUser;

	/** @var AuthPlugin default authentication source, if specified */
	var $defaultAuth;

	/** @var boolean whether or not captcha is enabled for this form */
	var $captchaEnabled;

	/**
	 * Constructor.
	 */
	function __construct($site) {
		parent::__construct('frontend/pages/userRegister.tpl');

		// Validation checks for this form
		$form = $this;
		$this->addCheck(new FormValidatorCustom($this, 'username', 'required', 'user.register.form.usernameExists', array(DAORegistry::getDAO('UserDAO'), 'userExistsByUsername'), array(), true));
		$this->addCheck(new FormValidator($this, 'username', 'required', 'user.profile.form.usernameRequired'));
		$this->addCheck(new FormValidator($this, 'password', 'required', 'user.profile.form.passwordRequired'));
		$this->addCheck(new FormValidatorUsername($this, 'username', 'required', 'user.register.form.usernameAlphaNumeric'));
		$this->addCheck(new FormValidatorLength($this, 'password', 'required', 'user.register.form.passwordLengthRestriction', '>=', $site->getMinPasswordLength()));
		$this->addCheck(new FormValidatorCustom($this, 'password', 'required', 'user.register.form.passwordsDoNotMatch', function($password) use ($form) {
			return $password == $form->getData('password2');
		}));

		$this->addCheck(new FormValidator($this, 'givenName', 'required', 'user.profile.form.givenNameRequired'));

		$this->addCheck(new FormValidator($this, 'country', 'required', 'user.profile.form.countryRequired'));

		// Email checks
		$this->addCheck(new FormValidatorEmail($this, 'email', 'required', 'user.profile.form.emailRequired'));
		$this->addCheck(new FormValidatorCustom($this, 'email', 'required', 'user.register.form.emailExists', array(DAORegistry::getDAO('UserDAO'), 'userExistsByEmail'), array(), true));

		$this->captchaEnabled = Config::getVar('captcha', 'captcha_on_register') && Config::getVar('captcha', 'recaptcha');
		if ($this->captchaEnabled) {
			$request = Application::get()->getRequest();
			$this->addCheck(new FormValidatorReCaptcha($this, $request->getRemoteAddr(), 'common.captcha.error.invalid-input-response', $request->getServerHost()));
		}

		$authDao = DAORegistry::getDAO('AuthSourceDAO'); /* @var $authDao AuthSourceDAO */
		$this->defaultAuth = $authDao->getDefaultPlugin();
		if (isset($this->defaultAuth)) {
			$auth = $this->defaultAuth;
			$this->addCheck(new FormValidatorCustom($this, 'username', 'required', 'user.register.form.usernameExists', function($username) use ($form, $auth) {
				return (!$auth->userExists($username) || $auth->authenticate($username, $form->getData('password')));
			}));
		}

		$context = Application::get()->getRequest()->getContext();
		if ($context && $context->getData('privacyStatement')) {
			$this->addCheck(new FormValidator($this, 'privacyConsent', 'required', 'user.profile.form.privacyConsentRequired'));
		}

		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = null, $display = false) {
		$templateMgr = TemplateManager::getManager($request);
		$site = $request->getSite();
		$context = $request->getContext();

		if ($this->captchaEnabled) {
			$publicKey = Config::getVar('captcha', 'recaptcha_public_key');
			$reCaptchaHtml = '<div class="g-recaptcha" data-sitekey="' . $publicKey . '"></div>';
			$templateMgr->assign(array(
				'reCaptchaHtml' => $reCaptchaHtml,
				'captchaEnabled' => true,
			));
		}

		$isoCodes = new \Sokil\IsoCodes\IsoCodesFactory();
		$countries = array();
		foreach ($isoCodes->getCountries() as $country) {
			$countries[$country->getAlpha2()] = $country->getLocalName();
		}
		asort($countries);
		$templateMgr->assign('countries', $countries);

		import('lib.pkp.classes.user.form.UserFormHelper');
		$userFormHelper = new UserFormHelper();
		$userFormHelper->assignRoleContent($templateMgr, $request);

		$templateMgr->assign(array(
			'source' =>$request->getUserVar('source'),
			'minPasswordLength' => $site->getMinPasswordLength(),
			'enableSiteWidePrivacyStatement' => Config::getVar('general', 'sitewide_privacy_statement'),
			'siteWidePrivacyStatement' => $site->getData('privacyStatement'),
		));

		return parent::fetch($request, $template, $display);
	}

	/**
	 * @copydoc Form::initData()
	 */
	function initData() {
		$this->_data = array(
			'userLocales' => array(),
			'userGroupIds' => array(),
		);
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		parent::readInputData();

		$this->readUserVars(array(
			'username',
			'password',
			'password2',
			'givenName',
			'familyName',
			'affiliation',
			'email',
			'country',
			'interests',
			'emailConsent',
			'privacyConsent',
			'readerGroup',
			'reviewerGroup',
		));

		if ($this->captchaEnabled) {
			$this->readUserVars(array(
				'g-recaptcha-response',
			));
		}

		// Collect the specified user group IDs into a single piece of data
		$this->setData('userGroupIds', array_merge(
			array_keys((array) $this->getData('readerGroup')),
			array_keys((array) $this->getData('reviewerGroup'))
		));
	}

	/**
	 * @copydoc Form::validate()
	 */
	function validate($callHooks = true) {
		$request = Application::get()->getRequest();

		// Ensure the consent checkbox has been completed for the site and any user
		// group signups if we're in the site-wide registration form
		if (!$request->getContext()) {

			if ($request->getSite()->getData('privacyStatement')) {
				$privacyConsent = $this->getData('privacyConsent');
				if (!is_array($privacyConsent) || !array_key_exists(CONTEXT_ID_NONE, $privacyConsent)) {
					$this->addError('privacyConsent[' . CONTEXT_ID_NONE . ']', __('user.register.form.missingSiteConsent'));
				}
			}

			if (!Config::getVar('general', 'sitewide_privacy_statement')) {
				$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
				$contextIds = array();
				foreach ($this->getData('userGroupIds') as $userGroupId) {
					$userGroup = $userGroupDao->getById($userGroupId);
					$contextIds[] = $userGroup->getContextId();
				}

				$contextIds = array_unique($contextIds);
				if (!empty($contextIds)) {
					$contextDao = Application::getContextDao();
					$privacyConsent = (array) $this->getData('privacyConsent');
					foreach ($contextIds as $contextId) {
						$context = $contextDao->getById($contextId);
						if ($context->getData('privacyStatement') && !array_key_exists($contextId, $privacyConsent)) {
							$this->addError('privacyConsent[' . $contextId . ']', __('user.register.form.missingContextConsent'));
							break;
						}
					}
				}
			}
		}

		return parent::validate($callHooks);
	}

	/**
	 * Register a new user.
	 * @return int|null User ID, or false on failure
	 */
	function execute(...$functionArgs) {
		$requireValidation = Config::getVar('email', 'require_validation');
		$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */

		// New user
		$this->user = $user = $userDao->newDataObject();

		$user->setUsername($this->getData('username'));

		// The multilingual user data (givenName, familyName and affiliation) will be saved
		// in the current UI locale and copied in the site's primary locale too
		$request = Application::get()->getRequest();
		$site = $request->getSite();
		$sitePrimaryLocale = $site->getPrimaryLocale();
		$currentLocale = AppLocale::getLocale();

		// Set the base user fields (name, etc.)
		$user->setGivenName($this->getData('givenName'), $currentLocale);
		$user->setFamilyName($this->getData('familyName'), $currentLocale);
		$user->setEmail($this->getData('email'));
		$user->setCountry($this->getData('country'));
		$user->setAffiliation($this->getData('affiliation'), $currentLocale);

		if ($sitePrimaryLocale != $currentLocale) {
			$user->setGivenName($this->getData('givenName'), $sitePrimaryLocale);
			$user->setFamilyName($this->getData('familyName'), $sitePrimaryLocale);
			$user->setAffiliation($this->getData('affiliation'), $sitePrimaryLocale);
		}

		$user->setDateRegistered(Core::getCurrentDate());
		$user->setInlineHelp(1); // default new users to having inline help visible.

		if (isset($this->defaultAuth)) {
			$user->setPassword($this->getData('password'));
			// FIXME Check result and handle failures
			$this->defaultAuth->doCreateUser($user);
			$user->setAuthId($this->defaultAuth->authId);
		}
		$user->setPassword(Validation::encryptCredentials($this->getData('username'), $this->getData('password')));

		if ($requireValidation) {
			// The account should be created in a disabled
			// state.
			$user->setDisabled(true);
			$user->setDisabledReason(__('user.login.accountNotValidated', array('email' => $this->getData('email'))));
		}

		parent::execute(...$functionArgs);

		$userDao->insertObject($user);
		$userId = $user->getId();
		if (!$userId) {
			return false;
		}

		// Associate the new user with the existing session
		$sessionManager = SessionManager::getManager();
		$session = $sessionManager->getUserSession();
		$session->setSessionVar('username', $user->getUsername());

		// Save the selected roles or assign the Reader role if none selected
		if ($request->getContext() && !$this->getData('reviewerGroup')) {
			$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
			$defaultReaderGroup = $userGroupDao->getDefaultByRoleId($request->getContext()->getId(), ROLE_ID_READER);
			if ($defaultReaderGroup) $userGroupDao->assignUserToGroup($user->getId(), $defaultReaderGroup->getId(), $request->getContext()->getId());
		} else {
			import('lib.pkp.classes.user.form.UserFormHelper');
			$userFormHelper = new UserFormHelper();
			$userFormHelper->saveRoleContent($this, $user);
		}

		// Save the email notification preference
		if ($request->getContext() && !$this->getData('emailConsent')) {

			// Get the public notification types
			import('classes.notification.form.NotificationSettingsForm');
			$notificationSettingsForm = new NotificationSettingsForm();
			$notificationCategories = $notificationSettingsForm->getNotificationSettingCategories();
			foreach ($notificationCategories as $notificationCategory) {
				if ($notificationCategory['categoryKey'] === 'notification.type.public') {
					$publicNotifications = $notificationCategory['settings'];
				}
			}
			if (isset($publicNotifications)) {
				$notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO'); /* @var $notificationSubscriptionSettingsDao NotificationSubscriptionSettingsDAO */
				$notificationSubscriptionSettingsDao->updateNotificationSubscriptionSettings(
					'blocked_emailed_notification',
					$publicNotifications,
					$user->getId(),
					$request->getContext()->getId()
				);
			}
		}

		// Insert the user interests
		import('lib.pkp.classes.user.InterestManager');
		$interestManager = new InterestManager();
		$interestManager->setInterestsForUser($user, $this->getData('interests'));

		import('lib.pkp.classes.mail.MailTemplate');
		if ($requireValidation) {
			// Create an access key
			import('lib.pkp.classes.security.AccessKeyManager');
			$accessKeyManager = new AccessKeyManager();
			$accessKey = $accessKeyManager->createKey('RegisterContext', $user->getId(), null, Config::getVar('email', 'validation_timeout'));

			// Send email validation request to user
			$mail = new MailTemplate('USER_VALIDATE');
			$this->_setMailFrom($request, $mail);
			$context = $request->getContext();
			$contextPath = $context ? $context->getPath() : null;
			$mail->assignParams(array(
				'userFullName' => $user->getFullName(),
				'contextName' => $context ? $context->getLocalizedName() : $site->getLocalizedTitle(),
				'activateUrl' => $request->url($contextPath, 'user', 'activateUser', array($this->getData('username'), $accessKey))
			));
			$mail->addRecipient($user->getEmail(), $user->getFullName());
			if (!$mail->send()) {
				import('classes.notification.NotificationManager');
				$notificationMgr = new NotificationManager();
				$notificationMgr->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => __('email.compose.error')));
			}
			unset($mail);
		}
		return $userId;
	}

	/**
	 * Set mail from address
	 * @param $request PKPRequest
	 * @param $mail MailTemplate
	 */
	function _setMailFrom($request, $mail) {
		$site = $request->getSite();
		$context = $request->getContext();

		// Set the sender based on the current context
		if ($context && $context->getData('supportEmail')) {
			$mail->setReplyTo($context->getData('supportEmail'), $context->getData('supportName'));
		} else {
			$mail->setReplyTo($site->getLocalizedContactEmail(), $site->getLocalizedContactName());
		}
	}
}
