<?php

/**
 * @file controllers/api/task/SendThankYouLinkAction.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SendThankYouLinkAction
 * @ingroup controllers_api_task
 *
 * @brief An action to open up a modal to send a thank you email to users assigned to a review task.
 */

import('lib.pkp.classes.linkAction.LinkAction');

class SendThankYouLinkAction extends LinkAction {

	/**
	 * Constructor
	 * @param $request Request
	 * @param $actionArgs array The action arguments.
	 */
	function SendThankYouLinkAction($request, $modalTitle, $actionArgs) {
		// Instantiate the send thank you modal.
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');

		$ajaxModal = new AjaxModal(
			$router->url($request, null, null, 'editThankReviewer', null, $actionArgs),
			__($modalTitle),
			'modal_email'
		);

		// Configure the link action.
		parent::LinkAction(
			'thankReviewer', $ajaxModal,
			__('editor.review.thankReviewer'),
			'accepted'
		);
	}
}

?>
