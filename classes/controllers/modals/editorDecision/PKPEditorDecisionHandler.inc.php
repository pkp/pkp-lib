<?php

/**
 * @file controllers/modals/editorDecision/EditorDecisionHandler.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
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
	function PKPEditorDecisionHandler() {
		parent::Handler();
	}


	//
	// Implement template methods from PKPHandler
	//
	/**
	 * @copydoc PKPHandler::authorize()
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
			LOCALE_COMPONENT_PKP_EDITOR,
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
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

		// Retrieve the current review round.
		$reviewRound = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ROUND);

		// Retrieve peer reviews.
		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		$submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO');
		$reviewFormResponseDao = DAORegistry::getDAO('ReviewFormResponseDAO');
		$reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO');

		$reviewAssignments = $reviewAssignmentDao->getBySubmissionId($submission->getId(), $reviewRound->getId());
		$reviewIndexes = $reviewAssignmentDao->getReviewIndexesForRound($submission->getId(), $reviewRound->getId());
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION);

		$body = '';
		$textSeparator = '------------------------------------------------------';
		foreach ($reviewAssignments as $reviewAssignment) {
			// If the reviewer has completed the assignment, then import the review.
			if ($reviewAssignment->getDateCompleted() != null && !$reviewAssignment->getCancelled()) {
				// Get the comments associated with this review assignment
				$submissionComments = $submissionCommentDao->getSubmissionComments($submission->getId(), COMMENT_TYPE_PEER_REVIEW, $reviewAssignment->getId());

				$body .= "\n\n$textSeparator\n";
				// If it is not a double blind review, show reviewer's name.
				if ($reviewAssignment->getReviewMethod() != SUBMISSION_REVIEW_METHOD_DOUBLEBLIND) {
					$body .= $reviewAssignment->getReviewerFullName() . "\n";
				} else {
					$body .= __('submission.comments.importPeerReviews.reviewerLetter', array('reviewerLetter' => String::enumerateAlphabetically($reviewIndexes[$reviewAssignment->getId()]))) . "\n";
				}

				while ($comment = $submissionComments->next()) {
					// If the comment is viewable by the author, then add the comment.
					if ($comment->getViewable()) {
						$body .= String::html2text($comment->getComments()) . "\n\n";
					}
				}
				$body .= "$textSeparator\n\n";

				if ($reviewFormId = $reviewAssignment->getReviewFormId()) {
					$reviewId = $reviewAssignment->getId();


					$reviewFormElements = $reviewFormElementDao->getByReviewFormId($reviewFormId);
					if(!$submissionComments) {
						$body .= "$textSeparator\n";

						$body .= __('submission.comments.importPeerReviews.reviewerLetter', array('reviewerLetter' => String::enumerateAlphabetically($reviewIndexes[$reviewAssignment->getId()]))) . "\n\n";
					}
					foreach ($reviewFormElements as $reviewFormElement) {
						$body .= String::html2text($reviewFormElement->getLocalizedQuestion()) . ": \n";
						$reviewFormResponse = $reviewFormResponseDao->getReviewFormResponse($reviewId, $reviewFormElement->getId());

						if ($reviewFormResponse) {
							$possibleResponses = $reviewFormElement->getLocalizedPossibleResponses();
							if (in_array($reviewFormElement->getElementType(), $reviewFormElement->getMultipleResponsesElementTypes())) {
								if ($reviewFormElement->getElementType() == REVIEW_FORM_ELEMENT_TYPE_CHECKBOXES) {
									foreach ($reviewFormResponse->getValue() as $value) {
										$body .= "\t" . String::htmltext($possibleResponses[$value-1]['content']) . "\n";
									}
								} else {
									$body .= "\t" . String::html2text($possibleResponses[$reviewFormResponse->getValue()-1]['content']) . "\n";
								}
								$body .= "\n";
							} else {
								$body .= "\t" . String::html2text($reviewFormResponse->getValue()) . "\n\n";
							}
						}

					}
					$body .= "$textSeparator\n\n";

				}


			}
		}

		if(empty($body)) {
			$json = new JSONMessage(false, __('editor.review.noReviews'));
		} else {
			$json = new JSONMessage(true, $body);
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
		return array('promoteInReview', 'savePromoteInReview', 'newReviewRound', 'saveNewReviewRound', 'sendReviewsInReview', 'saveSendReviewsInReview', 'importPeerReviews');
	}

	/**
	 * Get the fully-qualified import name for the given form name.
	 * @param $formName Class name for the desired form.
	 * @return string
	 */
	protected function _resolveEditorDecisionForm($formName) {
		switch($formName) {
			case 'EditorDecisionWithEmailForm':
			case 'NewReviewRoundForm':
			case 'PromoteForm':
			case 'SendReviewsForm':
				return "lib.pkp.controllers.modals.editorDecision.form.$formName";
			default:
				assert(false);
		}
	}

	/**
	 * Get an instance of an editor decision form.
	 * @param $formName string
	 * @param $decision int
	 * @return EditorDecisionForm
	 */
	protected function _getEditorDecisionForm($formName, $decision) {
		// Retrieve the authorized submission.
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		// Retrieve the stage id
		$stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);

		import($this->_resolveEditorDecisionForm($formName));
		if (in_array($stageId, $this->_getReviewStages())) {
			$reviewRound = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ROUND);
			$editorDecisionForm = new $formName($submission, $decision, $stageId, $reviewRound);
			// We need a different save operation in review stages to authorize
			// the review round object.
			if (is_a($editorDecisionForm, 'PromoteForm')) {
				$editorDecisionForm->setSaveFormOperation('savePromoteInReview');
			} else if (is_a($editorDecisionForm, 'SendReviewsForm')) {
				$editorDecisionForm->setSaveFormOperation('saveSendReviewsInReview');
			}
		} else {
			$editorDecisionForm = new $formName($submission, $decision, $stageId);
		}

		if (is_a($editorDecisionForm, $formName)) {
			return $editorDecisionForm;
		} else {
			assert(false);
			return null;
		}
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
			$notificationTypes = $this->_getReviewNotificationTypes();
			$notificationTypes[] = $editorDecisionNotificationType;
			$notificationMgr->updateNotification(
				$request,
				$notificationTypes,
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

	/**
	 * Get review-related stage IDs.
	 * @return array
	 */
	protected function _getReviewStages() {
		assert(false);
	}

	/**
	 * Get review-related decision notifications.
	 * @return array
	 */
	protected function _getReviewNotificationTypes() {
		assert(false); // Subclasses to override
	}
}

?>
