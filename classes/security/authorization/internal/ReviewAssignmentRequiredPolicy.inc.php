<?php
/**
 * @file classes/security/authorization/internal/ReviewAssignmentRequiredPolicy.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReviewAssignmentRequiredPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Policy that ensures that the request contains a valid review assignment.
 */

import('lib.pkp.classes.security.authorization.DataObjectRequiredPolicy');

class ReviewAssignmentRequiredPolicy extends DataObjectRequiredPolicy {
	/**
	 * Constructor
	 * @param $request PKPRequest
	 * @param $args array request parameters
	 * @param $submissionParameterName string the request parameter we
	 *  expect the submission id in.
	 */
	function __construct($request, &$args, $parameterName = 'reviewAssignmentId', $operations = null) {
		parent::__construct($request, $args, $parameterName, 'user.authorization.invalidReviewAssignment', $operations);
	}

	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see DataObjectRequiredPolicy::dataObjectEffect()
	 */
	function dataObjectEffect() {
		$reviewId = (int)$this->getDataObjectId();
		if (!$reviewId) {
			$this->setAuthorizationDenialErrorCode(AUTHORIZATION_ERROR_BAD_REQUEST);
			return AUTHORIZATION_DENY;
		}

		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /* @var $reviewAssignmentDao ReviewAssignmentDAO */
		$reviewAssignment = $reviewAssignmentDao->getById($reviewId);
		if (!is_a($reviewAssignment, 'ReviewAssignment')) {
			$this->setAuthorizationDenialErrorCode(AUTHORIZATION_ERROR_NOT_FOUND);
			return AUTHORIZATION_DENY;
		}

		// Ensure that the review assignment actually belongs to the
		// authorized submission.
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		assert(is_a($submission, 'Submission'));
		if ($reviewAssignment->getSubmissionId() != $submission->getId()) {
			$this->setAuthorizationDenialErrorCode(AUTHORIZATION_ERROR_FORBIDDEN);
			return AUTHORIZATION_DENY;
		}

		// Ensure that the review assignment is for this workflow stage
		$stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
		if ($reviewAssignment->getStageId() != $stageId) {
			$this->setAuthorizationDenialErrorCode(AUTHORIZATION_ERROR_FORBIDDEN);
			return AUTHORIZATION_DENY;
		}

		// Save the review Assignment to the authorization context.
		$this->addAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT, $reviewAssignment);
		return AUTHORIZATION_PERMIT;
	}
}

?>
