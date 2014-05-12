<?php

/**
 * @file pages/user/ProfileHandler.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ProfileHandler
 * @ingroup pages_user
 *
 * @brief Handle requests for modifying user profiles.
 */


import('pages.user.UserHandler');

class ProfileHandler extends UserHandler {
	/**
	 * Constructor
	 */
	function ProfileHandler() {
		parent::UserHandler();
	}

	//
	// Implement template methods from PKPHandler
	//
	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		$operations = array('profile', 'saveProfile', 'changePassword', 'savePassword');

		// Site access policy
		import('lib.pkp.classes.security.authorization.PKPSiteAccessPolicy');
		$this->addPolicy(new PKPSiteAccessPolicy($request, $operations, SITE_ACCESS_ALL_ROLES));

		// User must be logged in
		import('lib.pkp.classes.security.authorization.UserRequiredPolicy');
		$this->addPolicy(new UserRequiredPolicy($request));

		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * Display form to edit user's profile.
	 */
	function profile($args, $request) {
		$this->setupTemplate($request, true);

		$user = $request->getUser();
		import('classes.user.form.ProfileForm');
		$profileForm = new ProfileForm($user);
		if ($profileForm->isLocaleResubmit()) {
			$profileForm->readInputData();
		} else {
			$profileForm->initData($request);
		}
		$profileForm->display($request);
	}

	/**
	 * Validate and save changes to user's profile.
	 */
	function saveProfile($args, $request) {
		$this->setupTemplate($request);
		$dataModified = false;
		$user = $request->getUser();

		import('classes.user.form.ProfileForm');
		$profileForm = new ProfileForm($user);
		$profileForm->readInputData();

		if ($request->getUserVar('uploadProfileImage')) {
			if (!$profileForm->uploadProfileImage()) {
				$profileForm->addError('profileImage', __('user.profile.form.profileImageInvalid'));
			}
			$dataModified = true;
		} else if ($request->getUserVar('deleteProfileImage')) {
			$profileForm->deleteProfileImage();
			$dataModified = true;
		}

		if (!$dataModified && $profileForm->validate()) {
			$profileForm->execute($request);
			$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
			$context = $request->getContext();
			if ($userGroupDao->userInAnyGroup($user->getId(), $context->getId())) {
				$request->redirect(null, 'dashboard');
			}	else {
				$request->redirect(null, 'index');
			}
		} else {
			$profileForm->display($request);
		}
	}

	/**
	 * Display form to change user's password.
	 */
	function changePassword($args, $request) {
		$this->setupTemplate($request, true);

		$user = $request->getUser();
		$site = $request->getSite();

		import('lib.pkp.classes.user.form.ChangePasswordForm');
		$passwordForm = new ChangePasswordForm($user, $site);
		$passwordForm->initData($args, $request);
		$passwordForm->display($args, $request);
	}

	/**
	 * Save user's new password.
	 */
	function savePassword($args, $request) {
		$this->setupTemplate($request, true);

		$user = $request->getUser();
		$site = $request->getSite();

		import('lib.pkp.classes.user.form.ChangePasswordForm');
		$passwordForm = new ChangePasswordForm($user, $site);
		$passwordForm->readInputData();

		$this->setupTemplate($request, true);
		if ($passwordForm->validate()) {
			$passwordForm->execute($request);
			$request->redirect(null, $request->getRequestedPage());

		} else {
			$passwordForm->display($args, $request);
		}
	}
}

?>
