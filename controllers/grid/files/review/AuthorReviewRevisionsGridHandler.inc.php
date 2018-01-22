<?php

/**
 * @file controllers/grid/files/review/AuthorReviewRevisionsGridHandler.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AuthorReviewRevisionsGridHandler
 * @ingroup controllers_grid_files_review
 *
 * @brief Display to authors the file revisions that they have uploaded.
 */
import('lib.pkp.controllers.grid.files.fileList.FileListGridHandler');

class AuthorReviewRevisionsGridHandler extends FileListGridHandler {

	/**
	 * Constructor
	 */
	function __construct() {
		import('lib.pkp.controllers.grid.files.review.ReviewGridDataProvider');
		parent::__construct(
				new ReviewGridDataProvider(SUBMISSION_FILE_REVIEW_REVISION), null, FILE_GRID_ADD | FILE_GRID_EDIT | FILE_GRID_DELETE
		);

		$this->addRoleAssignment(
				array(ROLE_ID_AUTHOR), array('fetchGrid', 'fetchRow', 'sendRevisions')
		);

		$this->setTitle('editor.submission.revisions');
	}

	/**
	 * @copydoc GridHandler::initialize()
	 */
	function initialize($request) {
		parent::initialize($request);
		// add 'send Revisions' grid action

		import('lib.pkp.classes.linkAction.LinkAction');
		import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');
		import('lib.pkp.classes.linkAction.request.PostAndRedirectAction');


		//return new SendRevisionsLinkAction($request, $linkParams);
		$router = $request->getRouter();
		$reviewRound = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ROUND);
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
		$submissionFiles = $submissionFileDao->getRevisionsByReviewRound($reviewRound, SUBMISSION_FILE_REVIEW_REVISION);
		if (!empty($submissionFiles)) {
			$params = array(
				'reviewRoundId' => $reviewRound->getId(),
				'submissionId' => $reviewRound->getSubmissionId(),
				'stageId' => $reviewRound->getStageId(),
			);
			$linkAction = new LinkAction(
					'sendRevisions', new RemoteActionConfirmationModal(
					$request->getSession(), __('editor.submissionReview.confirmSendRevisions'), __('editor.submissionReview.sendRevisions'), $router->url($request, null, 'grid.files.review.AuthorReviewRevisionsGridHandler', 'sendRevisions', null, $params, 'modal_delete'
					)), __('editor.submissionReview.sendRevisions'), 'sendrevisions'
			);

			$this->addAction($linkAction, GRID_ACTION_POSITION_BELOW);
		}
	}

	//
	// Public handler methods
	//
	/**
	 * Update review status 
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function sendRevisions($args, $request) {
		//check if there some revisions files associated to this round
		$reviewRound = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ROUND);
		$roundStatus = $reviewRound->getStatus();


		if ($roundStatus == REVIEW_ROUND_STATUS_REVISIONS_REQUESTED || $roundStatus == REVIEW_ROUND_STATUS_RESUBMITTED) {
			$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
			$submissionFiles = $submissionFileDao->getRevisionsByReviewRound($reviewRound, SUBMISSION_FILE_REVIEW_REVISION);
			if (!empty($submissionFiles)) {
				// update reviewRound status
				switch ($roundStatus) {
					case REVIEW_ROUND_STATUS_REVISIONS_REQUESTED:
						$reviewRound->setStatus(REVIEW_ROUND_STATUS_REVISIONS_SUBMITTED);
						break;
					case REVIEW_ROUND_STATUS_RESUBMITTED:
						$reviewRound->setStatus(REVIEW_ROUND_STATUS_SUBMISSION_RESUBMITTED);
						break;
				}
				$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
				$reviewRoundDao->updateObject($reviewRound);
				// update lastModified date for the submission
				$submissionDao = Application::getSubmissionDAO();
				$submission = $submissionDao->getById($reviewRound->getSubmissionId());
				$submission->stampModified();
				$submissionDao->updateObject($submission);
			}

			// Inform view that status has changed
			return DAO::getDataChangedEvent();
		}
	}

}

?>
