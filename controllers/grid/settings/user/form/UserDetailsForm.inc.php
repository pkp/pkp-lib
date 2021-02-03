<?php

/**
 * @file controllers/grid/settings/user/form/UserDetailsForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserDetailsForm
 * @ingroup controllers_grid_settings_user_form
 *
 * @brief Form for editing user profiles.
 */

import('lib.pkp.controllers.grid.settings.user.form.UserForm');

class UserDetailsForm extends UserForm {

	/** @var User */
	var $user;

	/** @var An optional author to base this user on */
	var $author;

	/**
	 * Constructor.
	 * @param $request PKPRequest
	 * @param $userId int optional
	 * @param $author Author optional
	 */
	function __construct($request, $userId = null, $author = null) {
		parent::__construct('controllers/grid/settings/user/form/userDetailsForm.tpl', $userId);

		if (isset($author)) {
			$this->author =& $author;
		} else {
			$this->author = null;
		}

		// the users register for the site, thus
		// the site primary locale is the required default locale
		$site = $request->getSite();
		$this->addSupportedFormLocale($site->getPrimaryLocale());

		// Validation checks for this form
		$form = $this;
		if ($userId == null) {
			$this->addCheck(new FormValidator($this, 'username', 'required', 'user.profile.form.usernameRequired'));
			$this->addCheck(new FormValidatorCustom($this, 'username', 'required', 'user.register.form.usernameExists', array(DAORegistry::getDAO('UserDAO'), 'userExistsByUsername'), array($this->userId, true), true));
			$this->addCheck(new FormValidatorUsername($this, 'username', 'required', 'user.register.form.usernameAlphaNumeric'));

			if (!Config::getVar('security', 'implicit_auth')) {
				$this->addCheck(new FormValidator($this, 'password', 'required', 'user.profile.form.passwordRequired'));
				$this->addCheck(new FormValidatorCustom($this, 'password', 'required', 'user.register.form.passwordLengthRestriction', function($password) use ($form, $site) {
					return $form->getData('generatePassword') || PKPString::strlen($password) >= $site->getMinPasswordLength();
				}, array(), false, array('length' => $site->getMinPasswordLength())));
				$this->addCheck(new FormValidatorCustom($this, 'password', 'required', 'user.register.form.passwordsDoNotMatch', function($password) use ($form) {
					return $password == $form->getData('password2');
				}));
			}
		} else {
			$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
			$this->user = $userDao->getById($userId);

			$this->addCheck(new FormValidatorCustom($this, 'password', 'optional', 'user.register.form.passwordLengthRestriction', function($password) use ($form, $site) {
				return $form->getData('generatePassword') || PKPString::strlen($password) >= $site->getMinPasswordLength();
			}, array(), false, array('length' => $site->getMinPasswordLength())));
			$this->addCheck(new FormValidatorCustom($this, 'password', 'optional', 'user.register.form.passwordsDoNotMatch', function($password) use ($form) {
				return $password == $form->getData('password2');
			}));
		}
		$this->addCheck(new FormValidatorLocale($this, 'givenName', 'required', 'user.profile.form.givenNameRequired', $site->getPrimaryLocale()));
		$this->addCheck(new FormValidatorCustom($this, 'familyName', 'optional', 'user.profile.form.givenNameRequired.locale', function($familyName) use ($form) {
			$givenNames = $form->getData('givenName');
			foreach ($familyName as $locale => $value) {
				if (!empty($value) && empty($givenNames[$locale])) {
					return false;
				}
			}
			return true;
		}));
		$this->addCheck(new FormValidatorUrl($this, 'userUrl', 'optional', 'user.profile.form.urlInvalid'));
		$this->addCheck(new FormValidatorEmail($this, 'email', 'required', 'user.profile.form.emailRequired'));
		$this->addCheck(new FormValidatorCustom($this, 'email', 'required', 'user.register.form.emailExists', array(DAORegistry::getDAO('UserDAO'), 'userExistsByEmail'), array($this->userId, true), true));
		$this->addCheck(new FormValidatorORCID($this, 'orcid', 'optional', 'user.orcid.orcidInvalid'));
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	 * Initialize form data from current user profile.
	 */
	function initData() {
		$request = Application::get()->getRequest();
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : CONTEXT_ID_NONE;

		$data = array();

		if (isset($this->user)) {
			$user = $this->user;

			import('lib.pkp.classes.user.InterestManager');
			$interestManager = new InterestManager();

			$data = array(
				'authId' => $user->getAuthId(),
				'username' => $user->getUsername(),
				'givenName' => $user->getGivenName(null), // Localized
				'familyName' => $user->getFamilyName(null), // Localized
				'preferredPublicName' => $user->getPreferredPublicName(null), // Localized
				'signature' => $user->getSignature(null), // Localized
				'affiliation' => $user->getAffiliation(null), // Localized
				'email' => $user->getEmail(),
				'userUrl' => $user->getUrl(),
				'phone' => $user->getPhone(),
				'orcid' => $user->getOrcid(),
				'mailingAddress' => $user->getMailingAddress(),
				'country' => $user->getCountry(),
				'biography' => $user->getBiography(null), // Localized
				'interests' => $interestManager->getInterestsForUser($user),
				'userLocales' => $user->getLocales(),
			);
			import('classes.core.Services');
			$userService = Services::get('user');
			$data['canCurrentUserGossip'] = $userService->canCurrentUserGossip($user->getId());
			if ($data['canCurrentUserGossip']) {
				$data['gossip'] = $user->getGossip();
			}
		} else if (isset($this->author)) {
			$author = $this->author;
			$data = array(
				'givenName' => $author->getGivenName(null), // Localized
				'familyName' => $author->getFamilyName(null), // Localized
				'affiliation' => $author->getAffiliation(null), // Localized
				'preferredPublicName' => $author->getPreferredPublicName(null), // Localized
				'email' => $author->getEmail(),
				'userUrl' => $author->getUrl(),
				'orcid' => $author->getOrcid(),
				'country' => $author->getCountry(),
				'biography' => $author->getBiography(null), // Localized
			);
		} else {
			$data = array(
				'mustChangePassword' => true,
			);
		}
		foreach($data as $key => $value) {
			$this->setData($key, $value);
		}

		parent::initData();
	}

	/**
	 * @copydoc UserForm::display
	 */
	function display($request = null, $template = null) {
		$site = $request->getSite();
		$isoCodes = new \Sokil\IsoCodes\IsoCodesFactory();
		$countries = array();
		foreach ($isoCodes->getCountries() as $country) {
			$countries[$country->getAlpha2()] = $country->getLocalName();
		}
		asort($countries);
		$templateMgr = TemplateManager::getManager($request);

		$templateMgr->assign(array(
			'minPasswordLength' => $site->getMinPasswordLength(),
			'source' => $request->getUserVar('source'),
			'userId' => $this->userId,
			'sitePrimaryLocale' => $site->getPrimaryLocale(),
			'availableLocales' => $site->getSupportedLocaleNames(),
			'countries' => $countries,
		));

		if (isset($this->user)) {
			$templateMgr->assign('username', $this->user->getUsername());
		}

		$authDao = DAORegistry::getDAO('AuthSourceDAO'); /* @var $authDao AuthSourceDAO */
		$authSources = $authDao->getSources();
		$authSourceOptions = array();
		foreach ($authSources->toArray() as $auth) {
			$authSourceOptions[$auth->getAuthId()] = $auth->getTitle();
		}
		if (!empty($authSourceOptions)) {
			$templateMgr->assign('authSourceOptions', $authSourceOptions);
		}

		return parent::display($request, $template);
	}


	/**
	 * Assign form data to user-submitted data.
	 * @see Form::readInputData()
	 */
	function readInputData() {
		parent::readInputData();

		$this->readUserVars(array(
			'authId',
			'password',
			'password2',
			'givenName',
			'familyName',
			'preferredPublicName',
			'signature',
			'affiliation',
			'email',
			'userUrl',
			'phone',
			'orcid',
			'mailingAddress',
			'country',
			'biography',
			'gossip',
			'interests',
			'userLocales',
			'generatePassword',
			'sendNotify',
			'mustChangePassword'
		));
		if ($this->userId == null) {
			$this->readUserVars(array('username'));
		}

		if ($this->getData('userLocales') == null || !is_array($this->getData('userLocales'))) {
			$this->setData('userLocales', array());
		}
	}

	/**
	 * Get all locale field names
	 */
	function getLocaleFieldNames() {
		$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
		return $userDao->getLocaleFieldNames();
	}

	/**
	 * Create or update a user.
	 */
	function execute(...$functionParams) {
		$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
		$request = Application::get()->getRequest();
		$context = $request->getContext();

		if (!isset($this->user)) {
			$this->user = $userDao->newDataObject();
			$this->user->setInlineHelp(1); // default new users to having inline help visible
		}

		$this->user->setGivenName($this->getData('givenName'), null); // Localized
		$this->user->setFamilyName($this->getData('familyName'), null); // Localized
		$this->user->setPreferredPublicName($this->getData('preferredPublicName'), null); // Localized
		$this->user->setAffiliation($this->getData('affiliation'), null); // Localized
		$this->user->setSignature($this->getData('signature'), null); // Localized
		$this->user->setEmail($this->getData('email'));
		$this->user->setUrl($this->getData('userUrl'));
		$this->user->setPhone($this->getData('phone'));
		$this->user->setOrcid($this->getData('orcid'));
		$this->user->setMailingAddress($this->getData('mailingAddress'));
		$this->user->setCountry($this->getData('country'));
		$this->user->setBiography($this->getData('biography'), null); // Localized
		$this->user->setMustChangePassword($this->getData('mustChangePassword') ? 1 : 0);
		$this->user->setAuthId((int) $this->getData('authId'));
		// Users can never view/edit their own gossip fields
		import('classes.core.Services');
		$userService = Services::get('user');
		if ($userService->canCurrentUserGossip($this->user->getId())) {
			$this->user->setGossip($this->getData('gossip'));
		}

		$site = $request->getSite();
		$availableLocales = $site->getSupportedLocales();

		$locales = array();
		foreach ($this->getData('userLocales') as $locale) {
			if (AppLocale::isLocaleValid($locale) && in_array($locale, $availableLocales)) {
				array_push($locales, $locale);
			}
		}
		$this->user->setLocales($locales);

		if ($this->user->getAuthId()) {
			$authDao = DAORegistry::getDAO('AuthSourceDAO'); /* @var $authDao AuthSourceDAO */
			$auth =& $authDao->getPlugin($this->user->getAuthId());
		}

		parent::execute(...$functionParams);

		if ($this->user->getId() != null) {
			if ($this->getData('password') !== '') {
				if (isset($auth)) {
					$auth->doSetUserPassword($this->user->getUsername(), $this->getData('password'));
					$this->user->setPassword(Validation::encryptCredentials($this->user->getId(), Validation::generatePassword())); // Used for PW reset hash only
				} else {
					$this->user->setPassword(Validation::encryptCredentials($this->user->getUsername(), $this->getData('password')));
				}
			}

			if (isset($auth)) {
				// FIXME Should try to create user here too?
				$auth->doSetUserInfo($this->user);
			}

			$userDao->updateObject($this->user);

		} else {
			$this->user->setUsername($this->getData('username'));
			if ($this->getData('generatePassword')) {
				$password = Validation::generatePassword();
				$sendNotify = true;
			} else {
				$password = $this->getData('password');
				$sendNotify = $this->getData('sendNotify');
			}

			if (isset($auth)) {
				$this->user->setPassword($password);
				// FIXME Check result and handle failures
				$auth->doCreateUser($this->user);
				$this->user->setAuthId($auth->authId);
				$this->user->setPassword(Validation::encryptCredentials($this->user->getId(), Validation::generatePassword())); // Used for PW reset hash only
			} else {
				$this->user->setPassword(Validation::encryptCredentials($this->getData('username'), $password));
			}

			$this->user->setDateRegistered(Core::getCurrentDate());
			$userId = $userDao->insertObject($this->user);

			if ($sendNotify) {
				// Send welcome email to user
				import('lib.pkp.classes.mail.MailTemplate');
				$mail = new MailTemplate('USER_REGISTER');
				$mail->setReplyTo($context->getData('contactEmail'), $context->getData('contactName'));
				$mail->assignParams(array('username' => $this->getData('username'), 'password' => $password, 'userFullName' => $this->user->getFullName()));
				$mail->addRecipient($this->user->getEmail(), $this->user->getFullName());
				if (!$mail->send()) {
					import('classes.notification.NotificationManager');
					$notificationMgr = new NotificationManager();
					$notificationMgr->createTrivialNotification($request->getUser()->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => __('email.compose.error')));
				}
			}
		}

		import('lib.pkp.classes.user.InterestManager');
		$interestManager = new InterestManager();
		$interestManager->setInterestsForUser($this->user, $this->getData('interests'));

		return $this->user;
	}
}


