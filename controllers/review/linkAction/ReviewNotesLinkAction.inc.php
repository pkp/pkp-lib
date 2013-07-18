<?php
/**
 * @defgroup controllers_review_linkAction
 */

/**
 * @file controllers/review/linkAction/ReviewInfoCenterLinkAction.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReviewInfoCenterLinkAction
 * @ingroup controllers_review_linkAction
 *
 * @brief An action to open up the review notes for a review assignments.
 */

import('lib.pkp.classes.linkAction.LinkAction');

class ReviewNotesLinkAction extends LinkAction {

	/**
	 * Constructor
	 * @param $request Request
	 * @param $reviewAssignment ReviewAssignment the review assignment
	 * to show information about.
	 * @param $submission Submission The reviewed submission.
	 * @param $user User The user.
	 */
	function ReviewNotesLinkAction($request, $reviewAssignment, $submission, $user) {
		// Instantiate the information center modal.
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		$actionArgs = array(
			'submissionId' => $reviewAssignment->getSubmissionId(),
			'reviewAssignmentId' => $reviewAssignment->getId(),
			'stageId' => $reviewAssignment->getStageId()
		);

		$ajaxModal = new AjaxModal(
			$router->url(
				$request, null,
				'grid.users.reviewer.ReviewerGridHandler', 'readReview',
				null, $actionArgs
			),
			__('editor.review') . ': ' . $submission->getLocalizedTitle(),
			'modal_information'
		);

		$viewsDao = DAORegistry::getDAO('ViewsDAO');
		$lastViewDate = $viewsDao->getLastViewDate(ASSOC_TYPE_REVIEW_RESPONSE, $reviewAssignment->getId(), $user->getId());

		$icon = ($lastViewDate) ? 'notes' : 'notes_new';
		// Configure the link action.
		parent::LinkAction(
			'readReview', $ajaxModal,
			'', $icon
		);
	}
}

?>
