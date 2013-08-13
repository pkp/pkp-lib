<?php

/**
 * @file controllers/grid/settings/user/form/UserDetailsForm.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UserDetailsForm
 * @ingroup controllers_grid_settings_user_form
 *
 * @brief Form for editing user profiles.
 */

import('lib.pkp.controllers.grid.settings.user.form.UserForm');

class UserDetailsForm extends UserForm {

	/** @var An optional author to base this user on */
	var $author;

	/**
	 * Constructor.
	 * @param $request PKPRequest
	 * @param $userId int optional
	 * @param $author Author optional
	 */
	function UserDetailsForm($request, $userId = null, $author = null) {
		parent::UserForm('controllers/grid/settings/user/form/userForm.tpl', $userId);

		if (isset($author)) {
			$this->author =& $author;
		} else {
			$this->author = null;
		}

		$site = $request->getSite();

		// Validation checks for this form
		if ($userId == null) {
			$this->addCheck(new FormValidator($this, 'username', 'required', 'user.profile.form.usernameRequired'));
			$this->addCheck(new FormValidatorCustom($this, 'username', 'required', 'user.register.form.usernameExists', array(DAORegistry::getDAO('UserDAO'), 'userExistsByUsername'), array($this->userId, true), true));
			$this->addCheck(new FormValidatorAlphaNum($this, 'username', 'required', 'user.register.form.usernameAlphaNumeric'));

			if (!Config::getVar('security', 'implicit_auth')) {
				$this->addCheck(new FormValidator($this, 'password', 'required', 'user.profile.form.passwordRequired'));
				$this->addCheck(new FormValidatorLength($this, 'password', 'required', 'user.register.form.passwordLengthTooShort', '>=', $site->getMinPasswordLength()));
				$this->addCheck(new FormValidatorCustom($this, 'password', 'required', 'user.register.form.passwordsDoNotMatch', create_function('$password,$form', 'return $password == $form->getData(\'password2\');'), array($this)));
			}
		} else {
			$this->addCheck(new FormValidatorLength($this, 'password', 'optional', 'user.register.form.passwordLengthTooShort', '>=', $site->getMinPasswordLength()));
			$this->addCheck(new FormValidatorCustom($this, 'password', 'optional', 'user.register.form.passwordsDoNotMatch', create_function('$password,$form', 'return $password == $form->getData(\'password2\');'), array($this)));
		}
		$this->addCheck(new FormValidator($this, 'firstName', 'required', 'user.profile.form.firstNameRequired'));
		$this->addCheck(new FormValidator($this, 'lastName', 'required', 'user.profile.form.lastNameRequired'));
		$this->addCheck(new FormValidatorUrl($this, 'userUrl', 'optional', 'user.profile.form.urlInvalid'));
		$this->addCheck(new FormValidatorEmail($this, 'email', 'required', 'user.profile.form.emailRequired'));
		$this->addCheck(new FormValidatorCustom($this, 'email', 'required', 'user.register.form.emailExists', array(DAORegistry::getDAO('UserDAO'), 'userExistsByEmail'), array($this->userId, true), true));
		$this->addCheck(new FormValidatorPost($this));
	}

	/**
	 * Initialize form data from current user profile.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function initData($args, $request) {

		$data = array();

		if (isset($this->userId)) {
			$userDao = DAORegistry::getDAO('UserDAO');
			$user =& $userDao->getById($this->userId);

			import('lib.pkp.classes.user.InterestManager');
			$interestManager = new InterestManager();

			$data = array(
				'authId' => $user->getAuthId(),
				'username' => $user->getUsername(),
				'salutation' => $user->getSalutation(),
				'firstName' => $user->getFirstName(),
				'middleName' => $user->getMiddleName(),
				'lastName' => $user->getLastName(),
				'signature' => $user->getSignature(null), // Localized
				'initials' => $user->getInitials(),
				'gender' => $user->getGender(),
				'affiliation' => $user->getAffiliation(null), // Localized
				'email' => $user->getEmail(),
				'userUrl' => $user->getUrl(),
				'phone' => $user->getPhone(),
				'fax' => $user->getFax(),
				'mailingAddress' => $user->getMailingAddress(),
				'country' => $user->getCountry(),
				'biography' => $user->getBiography(null), // Localized
				'interestsKeywords' => $interestManager->getInterestsForUser($user),
				'interestsTextOnly' => $interestManager->getInterestsString($user),
				'userLocales' => $user->getLocales()
			);
		} else if (isset($this->author)) {
			$author =& $this->author;
			$data = array(
				'salutation' => $author->getSalutation(),
				'firstName' => $author->getFirstName(),
				'middleName' => $author->getMiddleName(),
				'lastName' => $author->getLastName(),
				'affiliation' => $author->getAffiliation(null), // Localized
				'email' => $author->getEmail(),
				'userUrl' => $author->getUrl(),
				'country' => $author->getCountry(),
				'biography' => $author->getBiography(null), // Localized
			);
		}
		foreach($data as $key => $value) {
			$this->setData($key, $value);
		}
	}

	/**
	 * Display the form.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function display($args, $request) {
		$site = $request->getSite();
		$templateMgr = TemplateManager::getManager($request);
		$userDao = DAORegistry::getDAO('UserDAO');

		$templateMgr->assign('genderOptions', $userDao->getGenderOptions());
		$templateMgr->assign('minPasswordLength', $site->getMinPasswordLength());
		$templateMgr->assign('source', $request->getUserVar('source'));
		$templateMgr->assign('userId', $this->userId);

		if (isset($this->userId)) {
			$user =& $userDao->getById($this->userId);
			$templateMgr->assign('username', $user->getUsername());
			$helpTopicId = 'context.users.index';
		} else {
			$helpTopicId = 'context.users.createNewUser';
		}

		$templateMgr->assign('implicitAuth', Config::getVar('security', 'implicit_auth'));
		$templateMgr->assign('availableLocales', $site->getSupportedLocaleNames());
		$templateMgr->assign('helpTopicId', $helpTopicId);

		$countryDao = DAORegistry::getDAO('CountryDAO');
		$templateMgr->assign('countries', $countryDao->getCountries());

		$authDao = DAORegistry::getDAO('AuthSourceDAO');
		$authSources =& $authDao->getSources();
		$authSourceOptions = array();
		foreach ($authSources->toArray() as $auth) {
			$authSourceOptions[$auth->getAuthId()] = $auth->getTitle();
		}
		if (!empty($authSourceOptions)) {
			$templateMgr->assign('authSourceOptions', $authSourceOptions);
		}
		// This parameters will be used by the js form handler to fetch a username suggestion.
		// In the js form handler the dummy strings will be replaced by the actual form fields values.
		$userNameParams = array('firstName' => 'FIRST_NAME_DUMMY', 'lastName' => 'LAST_NAME_DUMMY');
		$templateMgr->assign('suggestUsernameParams', $userNameParams);

		return $this->fetch($request);
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
			'salutation',
			'firstName',
			'middleName',
			'lastName',
			'gender',
			'initials',
			'signature',
			'affiliation',
			'email',
			'userUrl',
			'phone',
			'fax',
			'mailingAddress',
			'country',
			'biography',
			'keywords',
			'interestsTextOnly',
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
	 * Get all locale field names
	 */
	function getLocaleFieldNames() {
		$userDao = DAORegistry::getDAO('UserDAO');
		return $userDao->getLocaleFieldNames();
	}

	/**
	 * Create or update a user.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function &execute($args, $request) {
		parent::execute($request);

		$userDao = DAORegistry::getDAO('UserDAO');
		$context = $request->getContext();

		if (isset($this->userId)) {
			$userId = $this->userId;
			$user = $userDao->getById($userId);
		}

		if (!isset($user)) {
			$user = $userDao->newDataObject();
			$user->setInlineHelp(1); // default new users to having inline help visible
		}

		$user->setSalutation($this->getData('salutation'));
		$user->setFirstName($this->getData('firstName'));
		$user->setMiddleName($this->getData('middleName'));
		$user->setLastName($this->getData('lastName'));
		$user->setInitials($this->getData('initials'));
		$user->setGender($this->getData('gender'));
		$user->setAffiliation($this->getData('affiliation'), null); // Localized
		$user->setSignature($this->getData('signature'), null); // Localized
		$user->setEmail($this->getData('email'));
		$user->setUrl($this->getData('userUrl'));
		$user->setPhone($this->getData('phone'));
		$user->setFax($this->getData('fax'));
		$user->setMailingAddress($this->getData('mailingAddress'));
		$user->setCountry($this->getData('country'));
		$user->setBiography($this->getData('biography'), null); // Localized
		$user->setMustChangePassword($this->getData('mustChangePassword') ? 1 : 0);
		$user->setAuthId((int) $this->getData('authId'));

		$site = $request->getSite();
		$availableLocales = $site->getSupportedLocales();

		$locales = array();
		foreach ($this->getData('userLocales') as $locale) {
			if (AppLocale::isLocaleValid($locale) && in_array($locale, $availableLocales)) {
				array_push($locales, $locale);
			}
		}
		$user->setLocales($locales);

		if ($user->getAuthId()) {
			$authDao = DAORegistry::getDAO('AuthSourceDAO');
			$auth =& $authDao->getPlugin($user->getAuthId());
		}

		if ($user->getId() != null) {
			if ($this->getData('password') !== '') {
				if (isset($auth)) {
					$auth->doSetUserPassword($user->getUsername(), $this->getData('password'));
					$user->setPassword(Validation::encryptCredentials($user->getId(), Validation::generatePassword())); // Used for PW reset hash only
				} else {
					$user->setPassword(Validation::encryptCredentials($user->getUsername(), $this->getData('password')));
				}
			}

			if (isset($auth)) {
				// FIXME Should try to create user here too?
				$auth->doSetUserInfo($user);
			}

			$userDao->updateObject($user);

		} else {
			$user->setUsername($this->getData('username'));
			if ($this->getData('generatePassword')) {
				$password = Validation::generatePassword();
				$sendNotify = true;
			} else {
				$password = $this->getData('password');
				$sendNotify = $this->getData('sendNotify');
			}

			if (isset($auth)) {
				$user->setPassword($password);
				// FIXME Check result and handle failures
				$auth->doCreateUser($user);
				$user->setAuthId($auth->authId);
				$user->setPassword(Validation::encryptCredentials($user->getId(), Validation::generatePassword())); // Used for PW reset hash only
			} else {
				$user->setPassword(Validation::encryptCredentials($this->getData('username'), $password));
			}

			$user->setDateRegistered(Core::getCurrentDate());
			$userId = $userDao->insertObject($user);

			if ($sendNotify) {
				// Send welcome email to user
				import('lib.pkp.classes.mail.MailTemplate');
				$mail = new MailTemplate('USER_REGISTER');
				$mail->setReplyTo($context->getSetting('contactEmail'), $context->getSetting('contactName'));
				$mail->assignParams(array('username' => $this->getData('username'), 'password' => $password, 'userFullName' => $user->getFullName()));
				$mail->addRecipient($user->getEmail(), $user->getFullName());
				$mail->send();
			}
		}

		$interests = $this->getData('interestsKeywords') ? $this->getData('interestsKeywords') : $this->getData('interestsTextOnly');
		import('lib.pkp.classes.user.InterestManager');
		$interestManager = new InterestManager();
		$interestManager->setInterestsForUser($user, $interests);

		return $user;
	}
}

?>
