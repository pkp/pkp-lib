<?php
/**
 * @file classes/security/authorization/internal/ReviewAssignmentRequiredPolicy.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewAssignmentRequiredPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Policy that ensures that the request contains a valid review assignment.
 */

import('lib.pkp.classes.security.authorization.DataObjectRequiredPolicy');

class ReviewAssignmentRequiredPolicy extends DataObjectRequiredPolicy {

	/** @var Allowed review methods */
	var $_reviewMethods = array();

	/**
	 * Constructor
	 * @param $request PKPRequest
	 * @param $args array request parameters
	 * @param $parameterName string the request parameter we
	 *  expect the submission id in.
	 * @param $operations array|string either a single operation or a list of operations that
	 *  this policy is targeting.
	 * @param $reviewMethods array limit the policy to specific review methods
	 */
	function __construct($request, &$args, $parameterName = 'reviewAssignmentId', $operations = null, $reviewMethods = null) {
		parent::__construct($request, $args, $parameterName, 'user.authorization.invalidReviewAssignment', $operations, $reviewMethods);
		$this->_reviewMethods = $reviewMethods;
	}

	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see DataObjectRequiredPolicy::dataObjectEffect()
	 */
	function dataObjectEffect() {
		$reviewId = (int)$this->getDataObjectId();
		if (!$reviewId) return AUTHORIZATION_DENY;

		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /* @var $reviewAssignmentDao ReviewAssignmentDAO */
		$reviewAssignment = $reviewAssignmentDao->getById($reviewId);
		if (!is_a($reviewAssignment, 'ReviewAssignment')) return AUTHORIZATION_DENY;

		// If reviewMethods is defined, check that the assignment uses the defined method(s) 
		if ($this->_reviewMethods) {
			if (!in_array($reviewAssignment->getReviewMethod(), $this->_reviewMethods)) {
				return AUTHORIZATION_DENY;
			}
		}

		// Ensure that the review assignment actually belongs to the
		// authorized submission.
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		assert(is_a($submission, 'Submission'));
		if ($reviewAssignment->getSubmissionId() != $submission->getId()) return AUTHORIZATION_DENY;

		// Ensure that the review assignment is for this workflow stage
		$stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
		if ($reviewAssignment->getStageId() != $stageId) return AUTHORIZATION_DENY;

		// Save the review Assignment to the authorization context.
		$this->addAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT, $reviewAssignment);
		return AUTHORIZATION_PERMIT;
	}
}


