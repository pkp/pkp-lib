<?php
/**
 * @file classes/security/authorization/internal/ReviewAssignmentAccessPolicy.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
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
	var $_permitDeclinedOrCancelled;

	/**
	 * Constructor
	 * @param $request PKPRequest
	 * @param $permitDeclinedOrCancelled bool True if declined or cancelled reviews are acceptable.
	 */
	function __construct($request, $permitDeclinedOrCancelled = false) {
		parent::__construct('user.authorization.submissionReviewer');
		$this->_request = $request;
		$this->_permitDeclinedOrCancelled = $permitDeclinedOrCancelled;
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
		if (!is_a($user, 'User')) return AUTHORIZATION_DENY;

		// Get the submission
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		if (!is_a($submission, 'Submission')) return AUTHORIZATION_DENY;

		// Check if a review assignment exists between the submission and the user
		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /* @var $reviewAssignmentDao ReviewAssignmentDAO */
		$reviewAssignment = $reviewAssignmentDao->getLastReviewRoundReviewAssignmentByReviewer($submission->getId(), $user->getId());

		// Ensure a valid review assignment was fetched from the database
		if (!is_a($reviewAssignment, 'ReviewAssignment')) return AUTHORIZATION_DENY;

		// Ensure that the assignment isn't declined or cancelled, unless that's permitted
		if (!$this->_permitDeclinedOrCancelled && ($reviewAssignment->getDeclined())) return AUTHORIZATION_DENY;

		// Save the review assignment to the authorization context.
		$this->addAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT, $reviewAssignment);
		return AUTHORIZATION_PERMIT;
	}
}


