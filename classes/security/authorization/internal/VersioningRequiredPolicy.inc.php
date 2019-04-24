<?php
/**
 * @file classes/security/authorization/internal/VersioningRequiredPolicy.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class VersioningRequiredPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Policy that ensures that the request contains a submission version.
 */

import('lib.pkp.classes.security.authorization.DataObjectRequiredPolicy');

class VersioningRequiredPolicy extends DataObjectRequiredPolicy {
	var $_submission = null;
	var $_lookOnlyByParameterName = false;

	/**
	 * Constructor
	 * @param $request PKPRequest
	 * @param $args array request parameters
	 * @param $parameterName string the request parameter we expect the submission version in.
	 * @param $operations array Optional list of operations for which this check takes effect. If specified, operations outside this set will not be checked against this policy.
	 */
	function __construct($request, &$args, $parameterName = 'submissionVersion', $operations = null, $lookOnlyByParameterName = false) {
		parent::__construct($request, $args, $parameterName, 'user.authorization.invalidSubmissionVersion', $operations);

		$this->_lookOnlyByParameterName = $lookOnlyByParameterName;
	}

	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see DataObjectRequiredPolicy::dataObjectEffect()
	 */
	function dataObjectEffect() {
		/** @var $submission Submission*/
		if (!$submission = $this->getSubmission()) {
			return AUTHORIZATION_DENY;
		}

		if (!is_a($submission, 'Submission')) return AUTHORIZATION_DENY;

		// Get the submission version id.
		$submissionVersion = $this->getDataObjectId($this->_lookOnlyByParameterName);
		if ($submissionVersion === false)
			$submissionVersion = $submission->getSubmissionVersion();

		// Validate the submission version id.
		$submissionDao = Application::getSubmissionDAO();
		$submissionVersion = $submissionDao->getById($submission->getId(), null, false, $submissionVersion);
		if (!is_a($submissionVersion, 'Submission')) return AUTHORIZATION_DENY;

		// Save the submission version to the authorization context.
		$this->addAuthorizedContextObject(ASSOC_TYPE_SUBMISSION, $submissionVersion);

		return AUTHORIZATION_PERMIT;
	}

	function getSubmission() {
		return $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
	}

}
