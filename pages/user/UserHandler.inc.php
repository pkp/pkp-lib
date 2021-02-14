<?php

/**
 * @file pages/user/UserHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserHandler
 * @ingroup pages_user
 *
 * @brief Handle requests for user functions.
 */

import('lib.pkp.pages.user.PKPUserHandler');

class UserHandler extends PKPUserHandler {

	/**
	 * Determine if the server's setup has been sufficiently completed.
	 * @param $server Object
	 * @return boolean True iff setup is incomplete
	 */
	function _checkIncompleteSetup($server) {
		if($server->getLocalizedAcronym() == '' || $server->getData('contactEmail') == '' ||
		   $server->getData('contactName') == '' || $server->getLocalizedData('abbreviation') == '') {
			return true;
		} else return false;
	}

	/**
	 * Setup common template variables.
	 * @param $request PKPRequest
	 */
	function setupTemplate($request = null) {
		parent::setupTemplate($request);
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_AUTHOR, LOCALE_COMPONENT_APP_EDITOR, LOCALE_COMPONENT_APP_MANAGER, LOCALE_COMPONENT_PKP_GRID);
	}

}


