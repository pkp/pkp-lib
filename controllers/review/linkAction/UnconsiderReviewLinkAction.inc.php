<?php
/**
 * @defgroup controllers_review_linkAction Review Link Actions
 */

/**
 * @file controllers/review/linkAction/UnconsiderReviewLinkAction.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UnconsiderReviewLinkAction
 * @ingroup controllers_review_linkAction
 *
 * @brief An action to allow editors to unconsider a review.
 */

import('lib.pkp.classes.linkAction.LinkAction');

class UnconsiderReviewLinkAction extends LinkAction {

	/**
	 * Constructor
	 * @param $request Request
	 * @param $reviewAssignment ReviewAssignment the review assignment
	 * to show information about.
	 * @param $submission Submission The reviewed submission.
	 */
	function UnconsiderReviewLinkAction($request, $reviewAssignment, $submission) {
		// Instantiate the information center modal.
		$router = $request->getRouter();

		$actionArgs = array(
			'submissionId' => $reviewAssignment->getSubmissionId(),
			'reviewAssignmentId' => $reviewAssignment->getId(),
			'stageId' => $reviewAssignment->getStageId()
		);

		import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');
		$modal = new RemoteActionConfirmationModal(
			__('editor.review.unconsiderReviewText'), __('editor.review.unconsiderReview'),
			$router->url(
				$request, null,
				'grid.users.reviewer.ReviewerGridHandler', 'unconsiderReview',
				null, $actionArgs
			),
			'modal_information'
		);

		// Configure the link action.
		parent::LinkAction(
			'unconsiderReview', $modal,
			__('common.complete'),
			'completed'
		);
	}
}

?>
