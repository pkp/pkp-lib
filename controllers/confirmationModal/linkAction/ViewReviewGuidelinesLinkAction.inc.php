<?php
/**
 * @defgroup controllers_confirmationModal_linkAction Confirmation Modal Link Action
 */

/**
 * @file controllers/modals/submissionMetadata/linkAction/ViewReviewGuidelinesLinkAction.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ViewReviewGuidelinesLinkAction
 * @ingroup controllers_confirmationModal_linkAction
 *
 * @brief An action to open the review guidelines confirmation modal.
 */

import('lib.pkp.classes.linkAction.LinkAction');

class ViewReviewGuidelinesLinkAction extends LinkAction {

	/**
	 * Constructor
	 * @param $request Request
	 */
	function ViewReviewGuidelinesLinkAction($request) {
		$context = $request->getContext();
		// Instantiate the view review guidelines confirmation modal.
		import('lib.pkp.classes.linkAction.request.ConfirmationModal');
		$viewGuidelinesModal = new ConfirmationModal(
			$context->getLocalizedSetting('reviewGuidelines'),
			__('reviewer.submission.guidelines'),
			null, null,
			false
		);

		// Configure the link action.
		parent::LinkAction('viewReviewGuidelines', $viewGuidelinesModal, __('reviewer.submission.guidelines'));
	}
}

?>
