<?php

/**
 * @file classes/user/form/PKPProfileForm.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPProfileForm
 * @ingroup user_form
 *
 * @brief Form to edit user profile.
 */

import('lib.pkp.classes.form.Form');

class PKPProfileForm extends Form {

	/** @var User */
	var $_user;

	/**
	 * Constructor.
	 * @param $template string
	 * @param $user PKPUser
	 */
	function PKPProfileForm($user) {
		parent::Form('user/profile.tpl');

		$this->_user = $user;
		assert($user);

		// Validation checks for this form
		$this->addCheck(new FormValidator($this, 'firstName', 'required', 'user.profile.form.firstNameRequired'));
		$this->addCheck(new FormValidator($this, 'lastName', 'required', 'user.profile.form.lastNameRequired'));
		$this->addCheck(new FormValidatorUrl($this, 'userUrl', 'optional', 'user.profile.form.urlInvalid'));
		$this->addCheck(new FormValidatorEmail($this, 'email', 'required', 'user.profile.form.emailRequired'));
		$this->addCheck(new FormValidatorCustom($this, 'email', 'required', 'user.register.form.emailExists', array(DAORegistry::getDAO('UserDAO'), 'userExistsByEmail'), array($user->getId(), true), true));
		$this->addCheck(new FormValidator($this, 'country', 'required', 'user.profile.form.countryRequired'));
		$this->addCheck(new FormValidatorPost($this));
	}

	/**
	 * Get the user associated with this profile
	 */
	function getUser() {
		return $this->_user;
	}

	/**
	 * Delete a profile image.
	 */
	function deleteProfileImage() {
		$user = $this->getUser();
		$profileImage = $user->getSetting('profileImage');
		if (!$profileImage) return false;

		import('classes.file.PublicFileManager');
		$publicFileManager = new PublicFileManager();
		if ($publicFileManager->removeSiteFile($profileImage['uploadName'])) {
			return $user->updateSetting('profileImage', null);
		} else {
			return false;
		}
	}

	/**
	 * Upload a profile image.
	 */
	function uploadProfileImage() {
		import('classes.file.PublicFileManager');
		$publicFileManager = new PublicFileManager();

		$user = $this->getUser();

		$type = $publicFileManager->getUploadedFileType('profileImage');
		$extension = $publicFileManager->getImageExtension($type);
		if (!$extension) return false;

		$uploadName = 'profileImage-' . (int) $user->getId() . $extension;
		if (!$publicFileManager->uploadSiteFile('profileImage', $uploadName)) return false;

		$filePath = $publicFileManager->getSiteFilesPath();
		list($width, $height) = getimagesize($filePath . '/' . $uploadName);

		if ($width > 150 || $height > 150 || $width <= 0 || $height <= 0) {
			$userSetting = null;
			$user->updateSetting('profileImage', $userSetting);
			$publicFileManager->removeSiteFile($filePath);
			return false;
		}

		$userSetting = array(
			'name' => $publicFileManager->getUploadedFileName('profileImage'),
			'uploadName' => $uploadName,
			'width' => $width,
			'height' => $height,
			'dateUploaded' => Core::getCurrentDate()
		);

		$user->updateSetting('profileImage', $userSetting);
		return true;
	}

	/**
	 * Display the form.
	 */
	function display($request) {
		$templateMgr = TemplateManager::getManager($request);

		$user = $this->getUser();
		$templateMgr->assign('username', $user->getUsername());

		$site = $request->getSite();
		$templateMgr->assign('availableLocales', $site->getSupportedLocaleNames());


		$userDao = DAORegistry::getDAO('UserDAO');
		$templateMgr->assign('genderOptions', $userDao->getGenderOptions());

		$countryDao = DAORegistry::getDAO('CountryDAO');
		$countries = $countryDao->getCountries();
		$templateMgr->assign('countries', $countries);

		$templateMgr->assign('profileImage', $user->getSetting('profileImage'));

		$templateMgr = TemplateManager::getManager($request);

		$context = $request->getContext();
		if ($context) {
			$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
			$userGroupAssignmentDao = DAORegistry::getDAO('UserGroupAssignmentDAO');
			$userGroupAssignments = $userGroupAssignmentDao->getByUserId($user->getId(), $context->getId());
			$userGroupIds = array();
			while ($assignment = $userGroupAssignments->next()) {
				$userGroupIds[] = $assignment->getUserGroupId();
			}
			$templateMgr->assign('allowRegReviewer', $context->getSetting('allowRegReviewer'));
			$templateMgr->assign('reviewerUserGroups', $userGroupDao->getByRoleId($context->getId(), ROLE_ID_REVIEWER));
			$templateMgr->assign('allowRegAuthor', $context->getSetting('allowRegAuthor'));
			$templateMgr->assign('authorUserGroups', $userGroupDao->getByRoleId($context->getId(), ROLE_ID_AUTHOR));
			$templateMgr->assign('userGroupIds', $userGroupIds);
		}

		parent::display($request);
	}

	/**
	 * Get the localized elements for this form.
	 * @return array
	 */
	function getLocaleFieldNames() {
		$userDao = DAORegistry::getDAO('UserDAO');
		return $userDao->getLocaleFieldNames();
	}

	/**
	 * Initialize form data from current settings.
	 */
	function initData() {
		$user = $this->getUser();

		import('lib.pkp.classes.user.InterestManager');
		$interestManager = new InterestManager();

		$this->_data = array(
			'salutation' => $user->getSalutation(),
			'firstName' => $user->getFirstName(),
			'middleName' => $user->getMiddleName(),
			'initials' => $user->getInitials(),
			'lastName' => $user->getLastName(),
			'suffix' => $user->getSuffix(),
			'gender' => $user->getGender(),
			'affiliation' => $user->getAffiliation(null), // Localized
			'signature' => $user->getSignature(null), // Localized
			'email' => $user->getEmail(),
			'userUrl' => $user->getUrl(),
			'phone' => $user->getPhone(),
			'fax' => $user->getFax(),
			'mailingAddress' => $user->getMailingAddress(),
			'country' => $user->getCountry(),
			'biography' => $user->getBiography(null), // Localized
			'userLocales' => $user->getLocales(),
			'interestsKeywords' => $interestManager->getInterestsForUser($user),
			'interestsTextOnly' => $interestManager->getInterestsString($user)
		);
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array(
			'salutation',
			'firstName',
			'middleName',
			'lastName',
			'suffix',
			'gender',
			'initials',
			'affiliation',
			'signature',
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
			'authorGroup',
		));

		if ($this->getData('userLocales') == null || !is_array($this->getData('userLocales'))) {
			$this->setData('userLocales', array());
		}

		$keywords = $this->getData('keywords');
		if ($keywords != null && is_array($keywords['interests'])) {
			// The interests are coming in encoded -- Decode them for DB storage
			$this->setData('interestsKeywords', array_map('urldecode', $keywords['interests']));
		}
	}

	/**
	 * Save profile settings.
	 */
	function execute($request) {
		$user = $request->getUser();

		// User Groups
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$context = $request->getContext();
		if ($context) {
			foreach (array(
				array(
					'setting' => 'allowRegReviewer',
					'roleId' => ROLE_ID_REVIEWER,
					'formElement' => 'reviewerGroup'
				),
				array(
					'setting' => 'allowRegAuthor',
					'roleId' => ROLE_ID_AUTHOR,
					'formElement' => 'authorGroup'
				),
			) as $groupData) {
				$groupFormData = (array) $this->getData($groupData['formElement']);
				if (!$context->getSetting($groupData['setting'])) continue;
				$userGroups = $userGroupDao->getByRoleId($context->getId(), $groupData['roleId']);
				while ($userGroup = $userGroups->next()) {
					$groupId = $userGroup->getId();
					$inGroup = $userGroupDao->userInGroup($user->getId(), $groupId);
					if (!$inGroup && array_key_exists($groupId, $groupFormData)) {
						$userGroupDao->assignUserToGroup($user->getId(), $groupId, $context->getId());
					} elseif ($inGroup && !array_key_exists($groupId, $groupFormData)) {
						$userGroupDao->removeUserFromGroup($user->getId(), $groupId, $context->getId());
					}
				}
			}
		}

		$user->setSalutation($this->getData('salutation'));
		$user->setFirstName($this->getData('firstName'));
		$user->setMiddleName($this->getData('middleName'));
		$user->setLastName($this->getData('lastName'));
		$user->setSuffix($this->getData('suffix'));
		$user->setGender($this->getData('gender'));
		$user->setInitials($this->getData('initials'));
		$user->setAffiliation($this->getData('affiliation'), null); // Localized
		$user->setSignature($this->getData('signature'), null); // Localized
		$user->setEmail($this->getData('email'));
		$user->setUrl($this->getData('userUrl'));
		$user->setPhone($this->getData('phone'));
		$user->setFax($this->getData('fax'));
		$user->setMailingAddress($this->getData('mailingAddress'));
		$user->setCountry($this->getData('country'));
		$user->setBiography($this->getData('biography'), null); // Localized

		// Insert the user interests
		$interests = $this->getData('interestsKeywords') ? $this->getData('interestsKeywords') : $this->getData('interestsTextOnly');
		import('lib.pkp.classes.user.InterestManager');
		$interestManager = new InterestManager();
		$interestManager->setInterestsForUser($user, $interests);

		$site = $request->getSite();
		$availableLocales = $site->getSupportedLocales();

		$locales = array();
		foreach ($this->getData('userLocales') as $locale) {
			if (AppLocale::isLocaleValid($locale) && in_array($locale, $availableLocales)) {
				array_push($locales, $locale);
			}
		}
		$user->setLocales($locales);

		$userDao = DAORegistry::getDAO('UserDAO');
		$userDao->updateObject($user);

		if ($user->getAuthId()) {
			$authDao = DAORegistry::getDAO('AuthSourceDAO');
			$auth = $authDao->getPlugin($user->getAuthId());
		}

		if (isset($auth)) {
			$auth->doSetUserInfo($user);
		}
	}
}

?>
