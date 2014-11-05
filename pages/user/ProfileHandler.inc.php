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
		import('lib.pkp.classes.security.authorization.UserRequiredPolicy');
		$this->addPolicy(new UserRequiredPolicy($request));

		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * Display profile page.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function profile($args, $request) {
		$this->setupTemplate($request);
		$templateMgr = TemplateManager::getManager();
		$templateMgr->display('user/profile.tpl');
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
