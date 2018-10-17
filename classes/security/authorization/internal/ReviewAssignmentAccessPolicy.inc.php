<?php
/**
 * @file classes/security/authorization/internal/ReviewAssignmentAccessPolicy.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReviewAssignmentAccessPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Class to control access to a submission based on whether the user is an assigned reviewer.
 *
 * NB: This policy expects a previously authorized submission in the
 * authorization context.
 */

import('lib.pkp.classes.security.authorization.AuthorizationPolicy');

class ReviewAssignmentAccessPolicy extends AuthorizationPolicy {
	/** @var PKPRequest */
	var $_request;

	/** @var bool */
	var $_permitDeclined;

	/**
	 * Constructor
	 * @param $request PKPRequest
	 * @param $permitDeclined bool True if declined reviews are acceptable.
	 */
	function __construct($request, $permitDeclined = false) {
		parent::__construct('user.authorization.submissionReviewer');
		$this->_request = $request;
		$this->_permitDeclined = $permitDeclined;
	}

	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see AuthorizationPolicy::effect()
	 */
	function effect() {
		// Get the user
		$user = $this->_request->getUser();
		if (!is_a($user, 'PKPUser')) return AUTHORIZATION_DENY;

		// Get the submission
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		if (!is_a($submission, 'Submission')) return AUTHORIZATION_DENY;

		// Check if a review assignment exists between the submission and the user
		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /* @var $reviewAssignmentDao ReviewAssignmentDAO */
		$reviewAssignment = $reviewAssignmentDao->getLastReviewRoundReviewAssignmentByReviewer($submission->getId(), $user->getId());

		// Ensure a valid review assignment was fetched from the database
		if (!is_a($reviewAssignment, 'ReviewAssignment')) return AUTHORIZATION_DENY;

		// Ensure that the assignment isn't declined, unless that's permitted
		if (!$this->_permitDeclined && $reviewAssignment->getDeclined()) return AUTHORIZATION_DENY;

		// Save the review assignment to the authorization context.
		$this->addAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT, $reviewAssignment);
		return AUTHORIZATION_PERMIT;
	}
}


