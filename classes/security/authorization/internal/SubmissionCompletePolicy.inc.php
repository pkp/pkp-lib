<?php
/**
 * @file classes/security/authorization/internal/SubmissionCompletePolicy.inc.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionCompletePolicy
 * @ingroup security_authorization_internal
 *
 * @brief Class to control access to workflow only for complete submissions
 *
 */

import('lib.pkp.classes.security.authorization.DataObjectRequiredPolicy');

class SubmissionCompletePolicy extends DataObjectRequiredPolicy {

	/**
	 * Constructor
	 * 
	 * @param PKPRequest 	$request 					The PKP core request object
	 * @param array 		$args 						Request parameters
	 * @param string 		$submissionParameterName 	The request parameter we expect the submission id in.
	 * @param string 		$operation 					Optional list of operations for which this check takes effect. If specified, 
	 * 													operations outside this set will not be checked against this policy
	 */
	function __construct($request, &$args, $submissionParameterName = 'submissionId', $operations = null) {
		parent::__construct(
			$request,
			$args,
			$submissionParameterName,
			'user.authorization.submission.incomplete.workflowAccessRestrict',
			$operations
		);

		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_USER);
	}


	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see DataObjectRequiredPolicy::dataObjectEffect()
	 */
	public function dataObjectEffect() {
		$submissionId = $this->getDataObjectId();

		$submissionDao = DAORegistry::getDAO("SubmissionDAO"); /** @var SubmissionDAO $submissionDao */
		$submission = $submissionDao->getById($submissionId);

		if ($submission->getData('submissionProgress') > 0) {
			return AUTHORIZATION_DENY;
		}
		
		return AUTHORIZATION_PERMIT;
	}
}
