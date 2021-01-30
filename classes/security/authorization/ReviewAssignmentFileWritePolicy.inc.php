<?php
/**
 * @file classes/security/authorization/ReviewAssignmentFileWritePolicy.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewAssignmentFileWritePolicy
 * @ingroup security_authorization_internal
 *
 * @brief Authorize access to add, edit and delete reviewer attachments. This policy
 *   expects review round, submission, assigned workflow stages and user roles to be
 *   in the authorized context.
 */

import('lib.pkp.classes.security.authorization.AuthorizationPolicy');

class ReviewAssignmentFileWritePolicy extends AuthorizationPolicy {

	/** @var Request */
	private $_request;

	/** @var int */
	private $_reviewAssignmentId;

	/**
	 * Constructor
	 * @param $request PKPRequest
	 * @param $reviewAssignmentId int
	 */
	function __construct($request, $reviewAssignmentId) {
		parent::__construct('user.authorization.unauthorizedReviewAssignment');
		$this->_request = $request;
		$this->_reviewAssignmentId = $reviewAssignmentId;
	}

	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see AuthorizationPolicy::effect()
	 */
	function effect() {

		if (!$this->_reviewAssignmentId) {
			return AUTHORIZATION_DENY;
		}

		$reviewRound = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ROUND);
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$assignedStages = $this->getAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);
		$userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);

		if (!$reviewRound || !$submission) {
			return AUTHORIZATION_DENY;
		}

		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /* @var $noteDao ReviewAssignmentDAO */
		$reviewAssignment = $reviewAssignmentDao->getById($this->_reviewAssignmentId);

		if (!is_a($reviewAssignment, 'ReviewAssignment')) {
			return AUTHORIZATION_DENY;
		}

		// Review assignment, review round and submission must match
		if ($reviewAssignment->getReviewRoundId() != $reviewRound->getId()
				|| $reviewRound->getSubmissionId() != $submission->getId()) {
			return AUTHORIZATION_DENY;
		}

		// Managers can write review attachments when they are not assigned to a submission
		if (empty($stageAssignments) && in_array(ROLE_ID_MANAGER, $userRoles)) {
			$this->addAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT, $reviewAssignment);
			return AUTHORIZATION_PERMIT;
		}

		// Managers, editors and assistants can write review attachments when they are assigned
		// to the correct stage.
		if (!empty($assignedStages[$reviewRound->getStageId()])) {
			$allowedRoles = [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT];
			if (!empty(array_intersect($allowedRoles, $assignedStages[$reviewRound->getStageId()]))) {
				$this->addAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT, $reviewAssignment);
				return AUTHORIZATION_PERMIT;
			}
		}

		// Reviewers can write review attachments to their own review assigments,
		// if the assignment is not yet complete, cancelled or declined.
		if ($reviewAssignment->getReviewerId() == $this->_request->getUser()->getId()) {
			$notAllowedStatuses = [REVIEW_ASSIGNMENT_STATUS_DECLINED, REVIEW_ASSIGNMENT_STATUS_COMPLETE, REVIEW_ASSIGNMENT_STATUS_THANKED, REVIEW_ASSIGNMENT_STATUS_CANCELLED];
			if (!in_array($reviewAssignment->getStatus(), $notAllowedStatuses)) {
				$this->addAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT, $reviewAssignment);
				return AUTHORIZATION_PERMIT;
			}
		}

		return AUTHORIZATION_DENY;
	}
}
