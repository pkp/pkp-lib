<?php
/**
 * @file classes/linkAction/request/NullAction.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NullAction
 * @ingroup linkAction_request
 *
 * @brief This action does nothing.
 */


import('lib.pkp.classes.linkAction.request.LinkActionRequest');

class NullAction extends LinkActionRequest {

	//
	// Overridden protected methods from LinkActionRequest
	//
	/**
	 * @see LinkActionRequest::getJSLinkActionRequest()
	 */
	function getJSLinkActionRequest() {
		return '$.pkp.classes.linkAction.NullAction';
	}
}

?>
