<?php

/**
 * @file classes/user/form/PublicProfileForm.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PublicProfileForm
 * @ingroup user_form
 *
 * @brief Form to edit user's public profile.
 */

import('lib.pkp.classes.user.form.BaseProfileForm');
import('classes.file.PublicFileManager');

class PublicProfileForm extends BaseProfileForm {

	/**
	 * Constructor.
	 * @param $template string
	 * @param $user PKPUser
	 */
	function PublicProfileForm($user) {
		parent::BaseProfileForm('user/publicProfileForm.tpl', $user);

		// Validation checks for this form
		$this->addCheck(new FormValidatorORCID($this, 'orcid', 'optional', 'user.orcid.orcidInvalid'));
		$this->addCheck(new FormValidatorUrl($this, 'userUrl', 'optional', 'user.profile.form.urlInvalid'));
	}

	/**
	 * @copydoc Form::initData()
	 */
	function initData() {
		$user = $this->getUser();

		$this->_data = array(
			'orcid' => $user->getOrcid(),
			'userUrl' => $user->getUrl(),
			'biography' => $user->getBiography(null), // Localized
		);
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		parent::readInputData();

		$this->readUserVars(array(
			'orcid', 'userUrl', 'biography',
		));
	}

	/**
	 * Upload a profile image.
	 * @return boolean True iff success.
	 */
	function uploadProfileImage() {
		import('classes.file.PublicFileManager');
		$publicFileManager = new PublicFileManager();

		$user = $this->getUser();
		$type = $publicFileManager->getUploadedFileType('uploadedFile');
		$extension = $publicFileManager->getImageExtension($type);
		if (!$extension) return false;

		$uploadName = 'profileImage-' . (int) $user->getId() . $extension;
		if (!$publicFileManager->uploadSiteFile('uploadedFile', $uploadName)) return false;
		$filePath = $publicFileManager->getSiteFilesPath();
		list($width, $height) = getimagesize($filePath . '/' . $uploadName);

		if ($width > 150 || $height > 150 || $width <= 0 || $height <= 0) {
			$userSetting = null;
			$user->updateSetting('profileImage', $userSetting);
			$publicFileManager->removeSiteFile($filePath);
			return false;
		}

		$userSetting = array(
			'name' => $publicFileManager->getUploadedFileName('uploadedFile'),
			'uploadName' => $uploadName,
			'width' => $width,
			'height' => $height,
			'dateUploaded' => Core::getCurrentDate()
		);

		$user->updateSetting('profileImage', $userSetting);
		return true;
	}

	/**
	 * Delete a profile image.
	 * @return boolean True iff success.
	 */
	function deleteProfileImage() {
		$user = $this->getUser();
		$profileImage = $user->getSetting('profileImage');
		if (!$profileImage) return false;

		$publicFileManager = new PublicFileManager();
		if ($publicFileManager->removeSiteFile($profileImage['uploadName'])) {
			return $user->updateSetting('profileImage', null);
		} else {
			return false;
		}
	}

	/**
	 * Fetch the form.
	 * @param $request PKPRequest
	 * @return string JSON-encoded form contents.
	 */
	function fetch($request) {
		$templateMgr = TemplateManager::getManager($request);

		$publicFileManager = new PublicFileManager();
		$templateMgr->assign(array(
			'profileImage' => $request->getUser()->getSetting('profileImage'),
			'publicSiteFilesPath' => $publicFileManager->getSiteFilesPath(),
		));

		return parent::fetch($request);
	}

	/**
	 * Save public profile settings.
	 * @param $request PKPRequest
	 */
	function execute($request) {
		$user = $request->getUser();

		$user->setOrcid($this->getData('orcid'));
		$user->setUrl($this->getData('userUrl'));
		$user->setBiography($this->getData('biography'), null); // Localized

		parent::execute($request, $user);
	}
}

?>
