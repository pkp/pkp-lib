<?php

/**
 * @file pages/user/PKPUserHandler.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPUserHandler
 * @ingroup pages_user
 *
 * @brief Handle requests for user functions.
 */

import('classes.handler.Handler');

class PKPUserHandler extends Handler {
	/**
	 * Constructor
	 **/
	function PKPUserHandler() {
		parent::Handler();
	}

	/**
	 * Get keywords for reviewer interests autocomplete.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function getInterests($args, &$request) {
		// Get the input text used to filter on
		$filter = $request->getUserVar('term');

		import('lib.pkp.classes.user.InterestManager');
		$interestManager = new InterestManager();

		$interests = $interestManager->getAllInterests($filter);

		import('lib.pkp.classes.core.JSON');
		$json = new JSON(true, $interests);
		return $json->getString();
	}
}

?>
