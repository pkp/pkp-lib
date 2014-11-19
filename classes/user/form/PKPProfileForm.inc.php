<?php

/**
 * @file classes/user/form/PKPProfileForm.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPProfileForm
 * @ingroup user_form
 *
 * @brief Form to edit user profile.
 */

import('lib.pkp.classes.user.form.PKPUserForm');

class PKPProfileForm extends PKPUserForm {

	/** @var User */
	var $_user;

	/**
	 * Constructor.
	 * @param $template string
	 * @param $user PKPUser
	 */
	function PKPProfileForm($user) {
		parent::PKPUserForm('user/profile.tpl');

		$this->_user = $user;
		assert($user);

		// Validation checks for this form
		$this->_addBaseUserFieldChecks();
		$this->addCheck(new FormValidatorCustom($this, 'email', 'required', 'user.register.form.emailExists', array(DAORegistry::getDAO('UserDAO'), 'userExistsByEmail'), array($user->getId(), true), true));
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
		$templateMgr->assign('profileImage', $user->getSetting('profileImage'));

		$templateMgr = TemplateManager::getManager($request);

		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$userGroupAssignmentDao = DAORegistry::getDAO('UserGroupAssignmentDAO');
		$userGroupAssignments = $userGroupAssignmentDao->getByUserId($user->getId());
		$userGroupIds = array();
		while ($assignment = $userGroupAssignments->next()) {
			$userGroupIds[] = $assignment->getUserGroupId();
		}
		$templateMgr->assign('userGroupIds', $userGroupIds);

		parent::display($request);
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
			'interests' => $interestManager->getInterestsForUser($user),
		);
	}

	/**
	 * Save profile settings.
	 */
	function execute($request) {
		$user = $request->getUser();

		$this->_updateUserGroups($user);
		$this->_setBaseUserFields($user, $request);
		$this->_updateUserInterests($user);

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
