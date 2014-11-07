<?php
/**
 * @defgroup controllers_tab_user
 */

/**
 * @file controllers/tab/user/ProfileTabHandler.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ProfileTabHandler
 * @ingroup controllers_tab_user
 *
 * @brief Handle requests for user profile tabs.
 */


import('classes.handler.Handler');
import('lib.pkp.classes.core.JSONMessage');

class ProfileTabHandler extends Handler {
	/**
	 * Constructor
	 */
	function ProfileTabHandler() {
		parent::Handler();
	}

	//
	// Implement template methods from PKPHandler
	//
	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, $args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.UserRequiredPolicy');
		$this->addPolicy(new UserRequiredPolicy($request));
		
		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * @copydoc PKPHandler::setupTemplate()
	 */
	function setupTemplate($request) {
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_GRID);
		parent::setupTemplate($request);
	}


	//
	// Tabs operations.
	//
	/**
	 * Display profile tab content.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string 
	 */
	function profile($args, $request) {
		$this->setupTemplate($request);
		$user = $request->getUser();
	
		import('classes.user.form.ProfileForm');
		$profileForm = new ProfileForm($user);
		$profileForm->initData($request);

		$json = new JSONMessage(true, $profileForm->fetch($request));
		return $json->getString();
	}

	/**
	 * Fetch notifications tab content.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string
	 */
	function notificationSettings($args, $request) {
		$this->setupTemplate($request);

		$user = $request->getUser();
		import('classes.notification.form.NotificationSettingsForm');
		$notificationSettingsForm = new NotificationSettingsForm();
		$json = new JSONMessage(true, $notificationSettingsForm->fetch($request));
		return $json->getString();
	}

	/**
	 * Validate and save changes to user's profile.
	 * @param $args array
	 * @param $request PKPRequest
	 * return string
	 */
	function saveProfile($args, $request) {
		$this->setupTemplate($request);
		$dataModified = false;
		$user = $request->getUser();

		import('classes.user.form.ProfileForm');
		$profileForm = new ProfileForm($user);
		$profileForm->readInputData();

		// FIXME see bug #6770
		if ($request->getUserVar('uploadProfileImage')) {
			if (!$profileForm->uploadProfileImage()) {
				$profileForm->addError('profileImage', __('user.profile.form.profileImageInvalid'));
			}
			$dataModified = true;
		} else if ($request->getUserVar('deleteProfileImage')) {
			$profileForm->deleteProfileImage();
			$dataModified = true;
		}

		$json = new JSONMessage();
		$notificationMgr = new NotificationManager(); 

		if (!$dataModified && $profileForm->validate()) {
			$profileForm->execute($request);
			$notificationMgr->createTrivialNotification($user->getId());
		} else {	
			$json->setStatus(false); 
		}

		return $json->getString();
	}

	/**
	 * Save user notification settings.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string
	 */
	function saveSettings($args, $request) {
		$this->setupTemplate($request);

		import('classes.notification.form.NotificationSettingsForm');

		$notificationSettingsForm = new NotificationSettingsForm();
		$notificationSettingsForm->readInputData();

		$json = new JSONMessage();
		if ($notificationSettingsForm->validate()) {
			$notificationSettingsForm->execute($request);
			$user = $request->getUser();
			$notificationMgr = new NotificationManager();
			$notificationMgr->createTrivialNotification($user->getId());
		} else {
			$json->setStatus(false);
		}

		return $json->getString();
	}
}

?>
