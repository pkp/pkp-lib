<?php

/**
 * @file controllers/modals/editorDecision/EditorDecisionHandler.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EditorDecisionHandler
 * @ingroup controllers_modals_editorDecision
 *
 * @brief Handle requests for editors to make a decision
 */

import('classes.handler.Handler');

// import JSON class for use with all AJAX requests
import('lib.pkp.classes.core.JSONMessage');

class PKPEditorDecisionHandler extends Handler {
	/**
	 * Constructor.
	 */
	function EditorDecisionHandler() {
		parent::Handler();
	}


	//
	// Implement template methods from PKPHandler
	//
	/**
	 * @see PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		// Some operations need a review round id in request.
		$reviewRoundOps = $this->_getReviewRoundOps();
		import('lib.pkp.classes.security.authorization.internal.ReviewRoundRequiredPolicy');
		$this->addPolicy(new ReviewRoundRequiredPolicy($request, $args, 'reviewRoundId', $reviewRoundOps));

		// Approve proof need submission access policy.
		$router = $request->getRouter();
		if ($router->getRequestedOp($request) == 'saveApproveProof') {
			import('classes.security.authorization.SubmissionFileAccessPolicy');
			$this->addPolicy(new SubmissionFileAccessPolicy($request, $args, $roleAssignments, SUBMISSION_FILE_ACCESS_MODIFY));
		}

		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * @see PKPHandler::initialize()
	 */
	function initialize($request, $args) {
		AppLocale::requireComponents(
			LOCALE_COMPONENT_APP_COMMON,
			LOCALE_COMPONENT_APP_EDITOR,
			LOCALE_COMPONENT_PKP_SUBMISSION
		);
	}


	//
	// Public handler actions
	//
	/**
	 * Start a new review round
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function newReviewRound($args, $request) {
		return $this->_initiateEditorDecision($args, $request, 'NewReviewRoundForm');
	}

	/**
	 * Jump from submission to external review
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function externalReview($args, $request) {
		return $this->_initiateEditorDecision($args, $request, 'InitiateExternalReviewForm');
	}

	/**
	 * Start a new review round in external review, bypassing internal
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function saveExternalReview($args, $request) {
		assert($this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE) == WORKFLOW_STAGE_ID_SUBMISSION);
		return $this->_saveEditorDecision(
			$args, $request, 'InitiateExternalReviewForm',
			WORKFLOW_STAGE_PATH_EXTERNAL_REVIEW,
			SUBMISSION_EDITOR_DECISION_EXTERNAL_REVIEW
		);
	}

	/**
	 * Show a save review form (responsible for decline submission modals when not in review stage)
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function sendReviews($args, $request) {
		return $this->_initiateEditorDecision($args, $request, 'SendReviewsForm');
	}

	/**
	 * Show a save review form (responsible for request revisions,
	 * resubmit for review, and decline submission modals in review stages).
	 * We need this because the authorization in review stages is different
	 * when not in review stages (need to authorize review round id).
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function sendReviewsInReview($args, $request) {
		return $this->_initiateEditorDecision($args, $request, 'SendReviewsForm');
	}

	/**
	 * Save the send review form when user is not in review stage.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function saveSendReviews($args, $request) {
		return $this->_saveEditorDecision($args, $request, 'SendReviewsForm');
	}

	/**
	 * Save the send review form when user is in review stages.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function saveSendReviewsInReview($args, $request) {
		return $this->_saveEditorDecision($args, $request, 'SendReviewsForm');
	}

	/**
	 * Show a promote form (responsible for accept submission modals outside review stage)
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function promote($args, $request) {
		return $this->_initiateEditorDecision($args, $request, 'PromoteForm');
	}

	/**
	 * Show a promote form (responsible for external review and accept submission modals
	 * in review stages). We need this because the authorization for promoting in review
	 * stages is different when not in review stages (need to authorize review round id).
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function promoteInReview($args, $request) {
		return $this->_initiateEditorDecision($args, $request, 'PromoteForm');
	}

	/**
	 * Save the send review form
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function savePromote($args, $request) {
		return $this->_saveGeneralPromote($args, $request);
	}

	/**
	 * Save the send review form (same case of the
	 * promoteInReview() method, see description there).
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function savePromoteInReview($args, $request) {
		return $this->_saveGeneralPromote($args, $request);
	}

	/**
	 * Import all free-text/review form reviews to paste into message
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function importPeerReviews($args, $request) {
		// Retrieve the authorized submission.
		$seriesEditorSubmission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

		// Retrieve the current review round.
		$reviewRound = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ROUND);

		// Retrieve peer reviews.
		import('classes.submission.seriesEditor.SeriesEditorAction');
		$seriesEditorAction = new SeriesEditorAction();
		$peerReviews = $seriesEditorAction->getPeerReviews($seriesEditorSubmission, $reviewRound->getId());

		if(empty($peerReviews)) {
			$json = new JSONMessage(false, __('editor.review.noReviews'));
		} else {
			$json = new JSONMessage(true, $peerReviews);
		}
		return $json->getString();
	}


	//
	// Protected helper methods
	//
	/**
	 * Get operations that need a review round id policy.
	 * @return array
	 */
	protected function _getReviewRoundOps() {
		assert(false); // Subclasses to override
	}

	/**
	 * Initiate an editor decision.
	 * @param $args array
	 * @param $request PKPRequest
	 * @param $formName string Name of form to call
	 * @return string Serialized JSON object
	 */
	protected function _initiateEditorDecision($args, $request, $formName) {
		// Retrieve the decision
		$decision = (int)$request->getUserVar('decision');

		// Form handling
		$editorDecisionForm = $this->_getEditorDecisionForm($formName, $decision);
		$editorDecisionForm->initData($args, $request);

		$json = new JSONMessage(true, $editorDecisionForm->fetch($request));
		return $json->getString();
	}

	/**
	 * Save an editor decision.
	 * @param $args array
	 * @param $request PKPRequest
	 * @param $formName string Name of form to call
	 * @param $redirectOp string A workflow stage operation to
	 *  redirect to if successful (if any).
	 * @return string Serialized JSON object
	 */
	protected function _saveEditorDecision($args, $request, $formName, $redirectOp = null, $decision = null) {
		// Retrieve the authorized submission.
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		// Retrieve the decision
		if (is_null($decision)) {
			$decision = (int)$request->getUserVar('decision');
		}

		$editorDecisionForm = $this->_getEditorDecisionForm($formName, $decision);
		$editorDecisionForm->readInputData();
		if ($editorDecisionForm->validate()) {
			$editorDecisionForm->execute($args, $request);

			// Update editor decision and pending revisions notifications.
			$notificationMgr = new NotificationManager();
			$editorDecisionNotificationType = $this->_getNotificationTypeByEditorDecision($decision);
			$notificationMgr->updateNotification(
				$request,
				array($editorDecisionNotificationType,
					NOTIFICATION_TYPE_PENDING_INTERNAL_REVISIONS, NOTIFICATION_TYPE_PENDING_EXTERNAL_REVISIONS),
				array($submission->getUserId()),
				ASSOC_TYPE_SUBMISSION,
				$submission->getId()
			);

			$reviewRound = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ROUND);
			if ($reviewRound) {
				$notificationMgr->updateNotification(
					$request,
					array(NOTIFICATION_TYPE_ALL_REVIEWS_IN),
					null,
					ASSOC_TYPE_REVIEW_ROUND,
					$reviewRound->getId()
				);

				$notificationMgr->updateNotification(
					$request,
					array(NOTIFICATION_TYPE_ALL_REVISIONS_IN),
					null,
					ASSOC_TYPE_REVIEW_ROUND,
					$reviewRound->getId()
				);
			}

			if ($redirectOp) {
				$dispatcher = $this->getDispatcher();
				$redirectUrl = $dispatcher->url($request, ROUTE_PAGE, null, 'workflow', $redirectOp, array($submission->getId()));
				return $request->redirectUrlJson($redirectUrl);
			} else {
				// Needed to update review round status notifications.
				return DAO::getDataChangedEvent();
			}
		} else {
			$json = new JSONMessage(false);
		}
		return $json->getString();
	}
}

?>
