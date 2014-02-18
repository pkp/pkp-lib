<?php

/**
 * @file classes/submission/reviewer/form/ReviewerReviewStep3Form.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReviewerReviewStep3Form
 * @ingroup submission_reviewer_form
 *
 * @brief Form for Step 3 of a review.
 */

import('lib.pkp.classes.submission.reviewer.form.ReviewerReviewForm');

class ReviewerReviewStep3Form extends ReviewerReviewForm {
	/**
	 * Constructor.
	 * @param $reviewerSubmission ReviewerSubmission
	 * @param $reviewAssignment ReviewAssignment
	 */
	function ReviewerReviewStep3Form($request, $reviewerSubmission, $reviewAssignment) {
		parent::ReviewerReviewForm($request, $reviewerSubmission, $reviewAssignment, 3);

		// Validation checks for this form
		// FIXME #5123: Include when review form infrastructure is in place
		//$reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO');
		//$requiredReviewFormElementIds = $reviewFormElementDao->getRequiredReviewFormElementIds($this->reviewAssignment->getReviewFormId());
		//$this->addCheck(new FormValidatorCustom($this, 'reviewFormResponses', 'required', 'reviewer.submission.reviewFormResponse.form.responseRequired', create_function('$reviewFormResponses, $requiredReviewFormElementIds', 'foreach ($requiredReviewFormElementIds as $requiredReviewFormElementId) { if (!isset($reviewFormResponses[$requiredReviewFormElementId]) || $reviewFormResponses[$requiredReviewFormElementId] == \'\') return false; } return true;'), array($requiredReviewFormElementIds)));

		$this->addCheck(new FormValidatorPost($this));
	}


	function initData() {
		$reviewAssignment = $this->getReviewAssignment();
		// Retrieve reviewer comment.
		$submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO');
		$submissionComments = $submissionCommentDao->getReviewerCommentsByReviewerId($reviewAssignment->getReviewerId(), $reviewAssignment->getSubmissionId(), $reviewAssignment->getId());
		$this->setData('reviewerComment', $submissionComments->next());
	}

	//
	// Implement protected template methods from Form
	//
	/**
	 * @see Form::readInputData()
	 */
	function readInputData() {
		// FIXME #5123: Include when review form infrastructure is in place
		$this->readUserVars(
			array(/*'reviewFormResponses', */ 'comments', 'recommendation')
		);
	}

	/**
	 * @see Form::fetch()
	 */
	function fetch($request) {
		$templateMgr = TemplateManager::getManager($request);

		$reviewAssignment = $this->getReviewAssignment();
		$reviewRoundId = $reviewAssignment->getReviewRoundId();

		// Assign the objects and data to the template.
		$context = $this->request->getContext();
		$templateMgr->assign('reviewAssignment', $reviewAssignment);
		$templateMgr->assign('reviewRoundId', $reviewRoundId);

		// Include the review recommendation options on the form.
		$templateMgr->assign_by_ref('reviewerRecommendationOptions', ReviewAssignment::getReviewerRecommendationOptions());

		/*  FIXME #5123: Include when review form infrastructure is in place
		if($reviewAssignment->getReviewFormId()) {

			// Get the review form components
			$reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO');
			$reviewFormElements =& $reviewFormElementDao->getReviewFormElements($reviewAssignment->getReviewFormId());
			$reviewFormResponseDao = DAORegistry::getDAO('ReviewFormResponseDAO');
			$reviewFormResponses =& $reviewFormResponseDao->getReviewReviewFormResponseValues($reviewAssignment->getReviewId());
			$reviewFormDao = DAORegistry::getDAO('ReviewFormDAO');
			$reviewformid = $reviewAssignment->getReviewFormId();
			$reviewForm =& $reviewFormDao->getReviewForm($reviewAssignment->getReviewFormId(), ASSOC_TYPE_..., $context->getId());

			$templateMgr->assign_by_ref('reviewForm', $reviewForm);
			$templateMgr->assign('reviewFormElements', $reviewFormElements);
			$templateMgr->assign('reviewFormResponses', $reviewFormResponses);
			$templateMgr->assign('isLocked', isset($reviewAssignment) && $reviewAssignment->getDateCompleted() != null);
		}*/

		//
		// Assign the link actions
		//
		if ($context->getLocalizedSetting('reviewGuidelines')) {
			import('lib.pkp.controllers.confirmationModal.linkAction.ViewReviewGuidelinesLinkAction');
			$viewReviewGuidelinesAction = new ViewReviewGuidelinesLinkAction($request, $reviewAssignment->getStageId());
			$templateMgr->assign('viewGuidelinesAction', $viewReviewGuidelinesAction);
		}
		return parent::fetch($request);
	}

	/**
	 * @see Form::execute()
	 * @param $request PKPRequest
	 */
	function execute($request) {
		$reviewAssignment =& $this->getReviewAssignment();
		$notificationMgr = new NotificationManager();
		if($reviewAssignment->getReviewFormId()) {
			/* FIXME #5123: Include when review form infrastructure is in place
			$reviewFormResponseDao = DAORegistry::getDAO('ReviewFormResponseDAO');
			$reviewFormResponses = $this->getData('reviewFormResponses');
			if (is_array($reviewFormResponses)) foreach ($reviewFormResponses as $reviewFormElementId => $reviewFormResponseValue) {
				$reviewFormResponse =& $reviewFormResponseDao->getReviewFormResponse($reviewAssignment->getReviewId(), $reviewFormElementId);
				if (!isset($reviewFormResponse)) {
					$reviewFormResponse = new ReviewFormResponse();
				}
				$reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO');
				$reviewFormElement = $reviewFormElementDao->getReviewFormElement($reviewFormElementId);
				$elementType = $reviewFormElement->getElementType();
				switch ($elementType) {
					case REVIEW_FORM_ELEMENT_TYPE_SMALL_TEXT_FIELD:
					case REVIEW_FORM_ELEMENT_TYPE_TEXT_FIELD:
					case REVIEW_FORM_ELEMENT_TYPE_TEXTAREA:
						$reviewFormResponse->setResponseType('string');
						$reviewFormResponse->setValue($reviewFormResponseValue);
						break;
					case REVIEW_FORM_ELEMENT_TYPE_RADIO_BUTTONS:
					case REVIEW_FORM_ELEMENT_TYPE_DROP_DOWN_BOX:
						$reviewFormResponse->setResponseType('int');
						$reviewFormResponse->setValue($reviewFormResponseValue);
						break;
					case REVIEW_FORM_ELEMENT_TYPE_CHECKBOXES:
						$reviewFormResponse->setResponseType('object');
						$reviewFormResponse->setValue($reviewFormResponseValue);
						break;
				}
				if ($reviewFormResponse->getReviewFormElementId() != null && $reviewFormResponse->getReviewId() != null) {
					$reviewFormResponseDao->updateObject($reviewFormResponse);
				} else {
					$reviewFormResponse->setReviewFormElementId($reviewFormElementId);
					$reviewFormResponse->setReviewId($reviewAssignment->getReviewId());
					$reviewFormResponseDao->insertObject($reviewFormResponse);
				}
			} */
		} else {
			// Create a comment with the review.
			$submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO');
			$comment = $submissionCommentDao->newDataObject();
			$comment->setCommentType(COMMENT_TYPE_PEER_REVIEW);
			$comment->setRoleId(ROLE_ID_REVIEWER);
			$comment->setAssocId($reviewAssignment->getId());
			$comment->setSubmissionId($reviewAssignment->getSubmissionId());
			$comment->setAuthorId($reviewAssignment->getReviewerId());
			$comment->setComments($this->getData('comments'));
			$comment->setCommentTitle('');
			$comment->setViewable(true);
			$comment->setDatePosted(Core::getCurrentDate());

			// Persist the comment.
			$submissionCommentDao->insertObject($comment);

			$submissionDao = Application::getSubmissionDAO();
			$submission = $submissionDao->getById($reviewAssignment->getSubmissionId());

			$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
			$stageAssignments = $stageAssignmentDao->getBySubmissionAndStageId($submission->getId(), $submission->getStageId());

			while ($stageAssignment = $stageAssignments->next()) {
				$notificationMgr->createNotification(
					$request, $stageAssignment->getUserId(), NOTIFICATION_TYPE_REVIEWER_COMMENT,
					$submission->getContextId(), ASSOC_TYPE_REVIEW_ASSIGNMENT, $reviewAssignment->getId()
				);
			}
		}

		// Set review to next step.
		$this->updateReviewStepAndSaveSubmission($this->getReviewerSubmission());

		// Mark the review assignment as completed.
		$reviewAssignment->setDateCompleted(Core::getCurrentDate());
		$reviewAssignment->stampModified();

		// assign the recommendation to the review assignment, if there was one.
		$reviewAssignment->setRecommendation((int) $this->getData('recommendation'));

		// Persist the updated review assignment.
		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /* @var $reviewAssignmentDao ReviewAssignmentDAO */
		$reviewAssignmentDao->updateObject($reviewAssignment);

		// Update the review round status.
		$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /* @var $reviewRoundDao ReviewRoundDAO */
		$reviewRound = $reviewRoundDao->getById($reviewAssignment->getReviewRoundId());
		$reviewAssignments = $reviewAssignmentDao->getByReviewRoundId($reviewRound->getId(), true);
		$reviewRoundDao->updateStatus($reviewRound, $reviewAssignments);

		// Update "all reviews in" notification.
		$notificationMgr->updateNotification(
			$request,
			array(NOTIFICATION_TYPE_ALL_REVIEWS_IN),
			null,
			ASSOC_TYPE_REVIEW_ROUND,
			$reviewRound->getId()
		);

		// Remove the task
		$notificationDao = DAORegistry::getDAO('NotificationDAO');
		$notificationDao->deleteByAssoc(
			ASSOC_TYPE_REVIEW_ASSIGNMENT,
			$reviewAssignment->getId(),
			$reviewAssignment->getReviewerId(),
			NOTIFICATION_TYPE_REVIEW_ASSIGNMENT
		);
	}
}

?>
