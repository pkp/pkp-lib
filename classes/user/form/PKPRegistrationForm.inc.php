<?php
/**
 * @defgroup user_form User Forms
 */

/**
 * @file classes/user/form/PKPRegistrationForm.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPRegistrationForm
 * @ingroup user_form
 *
 * @brief Form for user registration.
 */

import('lib.pkp.classes.form.Form');

class PKPRegistrationForm extends Form {

	/** @var boolean user is already registered with another context */
	var $existingUser;

	/** @var AuthPlugin default authentication source, if specified */
	var $defaultAuth;

	/** @var boolean whether or not captcha is enabled for this form */
	var $captchaEnabled;

	/** @var boolean whether or not implicit authentication is used */
	var $implicitAuth;

	/**
	 * Constructor.
	 */
	function PKPRegistrationForm($site, $existingUser = false) {
		parent::Form('user/register.tpl');
		$this->implicitAuth = Config::getVar('security', 'implicit_auth');

		if ($this->implicitAuth) {
			// If implicit auth - it is always an existing user
			$this->existingUser = true;
		} else {
			$this->existingUser = $existingUser;

			$this->captchaEnabled = Config::getVar('captcha', 'captcha_on_register') && Config::getVar('captcha', 'recaptcha');

			// Validation checks for this form
			$this->addCheck(new FormValidator($this, 'username', 'required', 'user.profile.form.usernameRequired'));
			$this->addCheck(new FormValidator($this, 'password', 'required', 'user.profile.form.passwordRequired'));

			if ($this->existingUser) {
				// Existing user -- check login
				$this->addCheck(new FormValidatorCustom($this, 'username', 'required', 'user.login.loginError', create_function('$username,$form', 'return Validation::checkCredentials($form->getData(\'username\'), $form->getData(\'password\'));'), array(&$this)));
			} else {
				// New user -- check required profile fields
				$this->addCheck(new FormValidatorCustom($this, 'username', 'required', 'user.register.form.usernameExists', array(DAORegistry::getDAO('UserDAO'), 'userExistsByUsername'), array(), true));
				$this->addCheck(new FormValidatorAlphaNum($this, 'username', 'required', 'user.register.form.usernameAlphaNumeric'));
				$this->addCheck(new FormValidatorLength($this, 'password', 'required', 'user.register.form.passwordLengthTooShort', '>=', $site->getMinPasswordLength()));
				$this->addCheck(new FormValidatorCustom($this, 'password', 'required', 'user.register.form.passwordsDoNotMatch', create_function('$password,$form', 'return $password == $form->getData(\'password2\');'), array(&$this)));
				$this->addCheck(new FormValidator($this, 'firstName', 'required', 'user.profile.form.firstNameRequired'));
				$this->addCheck(new FormValidator($this, 'lastName', 'required', 'user.profile.form.lastNameRequired'));
				$this->addCheck(new FormValidatorUrl($this, 'userUrl', 'optional', 'user.profile.form.urlInvalid'));
				$this->addCheck(new FormValidatorEmail($this, 'email', 'required', 'user.profile.form.emailRequired'));
				$this->addCheck(new FormValidatorCustom($this, 'email', 'required', 'user.register.form.emailsDoNotMatch', create_function('$email,$form', 'return $email == $form->getData(\'confirmEmail\');'), array(&$this)));
				$this->addCheck(new FormValidatorCustom($this, 'email', 'required', 'user.register.form.emailExists', array(DAORegistry::getDAO('UserDAO'), 'userExistsByEmail'), array(), true));
				$this->addCheck(new FormValidator($this, 'country', 'required', 'user.profile.form.countryRequired'));
				if ($this->captchaEnabled) {
					$this->addCheck(new FormValidatorReCaptcha($this, 'recaptcha_challenge_field', 'recaptcha_response_field', Request::getRemoteAddr(), 'common.captchaField.badCaptcha'));
				}

				$authDao = DAORegistry::getDAO('AuthSourceDAO');
				$this->defaultAuth =& $authDao->getDefaultPlugin();
				if (isset($this->defaultAuth)) {
					$this->addCheck(new FormValidatorCustom($this, 'username', 'required', 'user.register.form.usernameExists', create_function('$username,$form,$auth', 'return (!$auth->userExists($username) || $auth->authenticate($username, $form->getData(\'password\')));'), array(&$this, $this->defaultAuth)));
				}
			}
		}

		$this->addCheck(new FormValidatorPost($this));
	}

	/**
	 * Display the form.
	 */
	function display($request) {
		$templateMgr = TemplateManager::getManager($request);
		$site = $request->getSite();
		$templateMgr->assign('minPasswordLength', $site->getMinPasswordLength());
		$context = $request->getContext();

		if ($this->captchaEnabled) {
			import('lib.pkp.lib.recaptcha.recaptchalib');
			$publicKey = Config::getVar('captcha', 'recaptcha_public_key');
			$useSSL = Config::getVar('security', 'force_ssl')?true:false;
			$reCaptchaHtml = recaptcha_get_html($publicKey, null, $useSSL);
			$templateMgr->assign('reCaptchaHtml', $reCaptchaHtml);
			$templateMgr->assign('captchaEnabled', true);
		}

		$countryDao = DAORegistry::getDAO('CountryDAO');
		$countries = $countryDao->getCountries();
		$templateMgr->assign('countries', $countries);

		$userDao = DAORegistry::getDAO('UserDAO');
		$templateMgr->assign('genderOptions', $userDao->getGenderOptions());

		if ($context) {
			$userGroupDao = DAORegistry::getDAO('UserGroupDAO');

			$templateMgr->assign('reviewerUserGroups', $userGroupDao->getByRoleId($context->getId(), ROLE_ID_REVIEWER));
			$templateMgr->assign('authorUserGroups', $userGroupDao->getByRoleId($context->getId(), ROLE_ID_AUTHOR));

			$templateMgr->assign('privacyStatement', $context->getLocalizedSetting('privacyStatement'));
			$templateMgr->assign('allowRegAuthor', $context->getSetting('allowRegAuthor'));
			$templateMgr->assign('allowRegReviewer', $context->getSetting('allowRegReviewer'));
		}

		$templateMgr->assign('source', $request->getUserVar('source'));

		$site = $request->getSite();
		$templateMgr->assign('availableLocales', $site->getSupportedLocaleNames());

		parent::display($request);
	}

	/**
	 * @see Form::getLocaleFieldNames
	 */
	function getLocaleFieldNames() {
		$userDao = DAORegistry::getDAO('UserDAO');
		return $userDao->getLocaleFieldNames();
	}

	/**
	 * Initialize default data.
	 */
	function initData() {
		$this->setData('existingUser', $this->existingUser);
		$this->setData('userLocales', array());
		$this->setData('sendPassword', false);
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$userVars = array(
			'username',
			'password',
			'password2',
			'salutation',
			'firstName',
			'middleName',
			'lastName',
			'suffix',
			'gender',
			'initials',
			'country',
			'affiliation',
			'email',
			'confirmEmail',
			'userUrl',
			'phone',
			'fax',
			'signature',
			'reviewerGroup',
			'authorGroup',
			'mailingAddress',
			'biography',
			'interestsTextOnly',
			'keywords',
			'userLocales',
			'registerAsReviewer',
			'existingUser',
			'sendPassword'
		);
		if ($this->captchaEnabled) {
			$userVars[] = 'recaptcha_challenge_field';
			$userVars[] = 'recaptcha_response_field';
		}

		$this->readUserVars($userVars);

		if ($this->getData('userLocales') == null || !is_array($this->getData('userLocales'))) {
			$this->setData('userLocales', array());
		}

		if ($this->getData('username') != null) {
			// Usernames must be lowercase
			$this->setData('username', strtolower($this->getData('username')));
		}

		$keywords = $this->getData('keywords');
		if ($keywords != null && is_array($keywords['interests'])) {
			// The interests are coming in encoded -- Decode them for DB storage
			$this->setData('interestsKeywords', array_map('urldecode', $keywords['interests']));
		}
	}

	/**
	 * Register a new user.
	 */
	function execute($request) {
		$requireValidation = Config::getVar('email', 'require_validation');
		$userDao = DAORegistry::getDAO('UserDAO');

		if ($this->existingUser) { // If using implicit auth - we hardwire that we are working on an existing user
			// Existing user in the system
			if ($this->implicitAuth) { // If we are using implicit auth - then use the session username variable - rather than data from the form
				$sessionManager = SessionManager::getManager();
				$session = $sessionManager->getUserSession();

				$user = $userDao->getByUsername($session->getSessionVar('username'));
			} else {
				$user = $userDao->getByUsername($this->getData('username'));
			}

			if (!$user) return false;
			$userId = $user->getId();

		} else {
			// New user
			$user = $userDao->newDataObject();

			$user->setUsername($this->getData('username'));
			$user->setSalutation($this->getData('salutation'));
			$user->setFirstName($this->getData('firstName'));
			$user->setMiddleName($this->getData('middleName'));
			$user->setInitials($this->getData('initials'));
			$user->setLastName($this->getData('lastName'));
			$user->setSuffix($this->getData('suffix'));
			$user->setGender($this->getData('gender'));
			$user->setAffiliation($this->getData('affiliation'), null); // Localized
			$user->setSignature($this->getData('signature'), null); // Localized
			$user->setEmail($this->getData('email'));
			$user->setUrl($this->getData('userUrl'));
			$user->setPhone($this->getData('phone'));
			$user->setFax($this->getData('fax'));
			$user->setMailingAddress($this->getData('mailingAddress'));
			$user->setBiography($this->getData('biography'), null); // Localized
			$user->setDateRegistered(Core::getCurrentDate());
			$user->setCountry($this->getData('country'));
			$user->setInlineHelp(1); // default new users to having inline help visible.

			$site = $request->getSite();
			$availableLocales = $site->getSupportedLocales();

			$locales = array();
			foreach ($this->getData('userLocales') as $locale) {
				if (AppLocale::isLocaleValid($locale) && in_array($locale, $availableLocales)) {
					array_push($locales, $locale);
				}
			}
			$user->setLocales($locales);

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
				$user->setDisabledReason(__('user.login.accountNotValidated'));
			}

			$userDao->insertObject($user);
			$userId = $user->getId();
			if (!$userId) {
				return false;
			}

			// Insert the user interests
			$interests = $this->getData('interestsKeywords') ? $this->getData('interestsKeywords') : $this->getData('interestsTextOnly');
			import('lib.pkp.classes.user.InterestManager');
			$interestManager = new InterestManager();
			$interestManager->setInterestsForUser($user, $interests);

			$sessionManager = SessionManager::getManager();
			$session = $sessionManager->getUserSession();
			$session->setSessionVar('username', $user->getUsername());
		}

		// User Groups
		$application = PKPApplication::getApplication();
		$request = $application->getRequest();
		$context = $request->getContext();
		if ($context) {
			$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
			if ($context->getSetting('allowRegReviewer')) {
				$reviewerGroup = $this->getData('reviewerGroup');
				$reviewerUserGroups = $userGroupDao->getByRoleId($context->getId(), ROLE_ID_REVIEWER);
				$reviewerUserGroups = $reviewerUserGroups->toAssociativeArray();

				if (is_array($reviewerGroup)) {
					foreach ($reviewerGroup as $groupId => $wantsGroup ) {
						// Validate group id.
						if (!isset($reviewerUserGroups[$groupId])) {
							fatalError('Invalid user group id!');
						}
						if ($wantsGroup && $reviewerUserGroups[$groupId]->getPermitSelfRegistration()) $userGroupDao->assignUserToGroup($userId, $groupId, $context->getId());
					}
				}
			}

			if ($context->getSetting('allowRegAuthor')) {
				$authorGroup = $this->getData('authorGroup');
				$authorUserGroups = $userGroupDao->getByRoleId($context->getId(), ROLE_ID_AUTHOR);
				$authorUserGroups = $authorUserGroups->toAssociativeArray();

				if (isset($authorGroup)) {
					// Validate group id.
					if (!isset($authorUserGroups[$authorGroup])) {
						fatalError('Invalid user group id!');
					}
					if ($authorUserGroups[$authorGroup]->getPermitSelfRegistration()) {
						$userGroupDao->assignUserToGroup($userId, $authorGroup, $context->getId());
					}
				}
			}
		}

		if (!$this->existingUser) {
			import('lib.pkp.classes.mail.MailTemplate');
			if ($requireValidation) {
				// Create an access key
				import('lib.pkp.classes.security.AccessKeyManager');
				$accessKeyManager = new AccessKeyManager();
				$accessKey = $accessKeyManager->createKey('RegisterContext', $user->getId(), null, Config::getVar('email', 'validation_timeout'));

				// Send email validation request to user
				$mail = new MailTemplate('USER_VALIDATE');
				$this->_setMailFrom($request, $mail);
				$mail->assignParams(array(
					'userFullName' => $user->getFullName(),
					'activateUrl' => $request->url($context->getPath(), 'user', 'activateUser', array($this->getData('username'), $accessKey))
				));
				$mail->addRecipient($user->getEmail(), $user->getFullName());
				$mail->send();
				unset($mail);
			}
			if ($this->getData('sendPassword')) {
				// Send welcome email to user
				$mail = new MailTemplate('USER_REGISTER');
				$this->_setMailFrom($request, $mail);
				$mail->assignParams(array(
					'username' => $this->getData('username'),
					'password' => String::substr($this->getData('password'), 0, 30), // Prevent mailer abuse via long passwords
					'userFullName' => $user->getFullName()
				));
				$mail->addRecipient($user->getEmail(), $user->getFullName());
				$mail->send();
				unset($mail);
			}
		}
	}

	/**
	 * Set mail from address
	 * @param $request PKPRequest
	 * @param MailTemplate $mail
	 */
	function _setMailFrom($request, &$mail) {
		$site = $request->getSite();
		$context = $request->getContext();

		// Set the sender based on the current context
		if ($context && $context->getSetting('supportEmail')) {
			$mail->setReplyTo($context->getSetting('supportEmail'), $context->getSetting('supportName'));
		} else {
			$mail->setReplyTo($site->getLocalizedContactEmail(), $site->getLocalizedContactName());
		}
	}
}

?>
