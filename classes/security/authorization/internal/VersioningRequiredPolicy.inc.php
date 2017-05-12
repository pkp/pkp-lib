<?php
/**
 * @file classes/security/authorization/internal/VersioningRequiredPolicy.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class VersioningRequiredPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Policy that ensures that the request contains a submission revision.
 */

import('lib.pkp.classes.security.authorization.DataObjectRequiredPolicy');

class VersioningRequiredPolicy extends DataObjectRequiredPolicy {
	/**
	 * Constructor
	 * @param $request PKPRequest
	 * @param $args array request parameters
	 * @param $parameterName string the request parameter we expect the submission revision in.
	 * @param $operations array Optional list of operations for which this check takes effect. If specified, operations outside this set will not be checked against this policy.
	 */
	function __construct($request, &$args, $parameterName = 'submissionRevision', $operations = null) {
		parent::__construct($request, $args, $parameterName, 'user.authorization.invalidSubmissionRevision', $operations);
	}

	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see DataObjectRequiredPolicy::dataObjectEffect()
	 */
	function dataObjectEffect() {

		// Get the submission revision id.
		$submissionRevisionId = $this->getDataObjectId();
		if ($submissionRevisionId === false) return AUTHORIZATION_DENY;

		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

		// Validate the submission revision id.
		$submissionDao = Application::getSubmissionDAO();
		$submissionRevision = $submissionDao->getById($submission->getId(), null, false, $submissionRevisionId);
		if (!is_a($submissionRevision, 'Submission')) return AUTHORIZATION_DENY;

		// Save the submission revision to the authorization context.
		$this->addAuthorizedContextObject(ASSOC_TYPE_SUBMISSION_REVISION, $submissionRevisionId);

		return AUTHORIZATION_PERMIT;
	}
}

?>
