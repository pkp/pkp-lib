<?php

/**
 * @file pages/user/ProfileHandler.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
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
		$operations = array(
			'profile',
		);

		// Site access policy
		import('lib.pkp.classes.security.authorization.PKPSiteAccessPolicy');
		$this->addPolicy(new PKPSiteAccessPolicy($request, $operations, SITE_ACCESS_ALL_ROLES));

		// User must be logged in
		import('lib.pkp.classes.security.authorization.UserRequiredPolicy');
		$this->addPolicy(new UserRequiredPolicy($request));

		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * Display user profile tabset.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function profile($args, $request) {
		$this->setupTemplate($request);

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->display('user/profile.tpl');
	}
}

?>
