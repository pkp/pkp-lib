<?php
/**
 * @file classes/security/authorization/internal/QueryRequiredPolicy.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class QueryRequiredPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Policy that ensures that the request contains a valid query.
 */

import('lib.pkp.classes.security.authorization.DataObjectRequiredPolicy');

class QueryRequiredPolicy extends DataObjectRequiredPolicy {
	/**
	 * Constructor
	 * @param $request PKPRequest
	 * @param $args array request parameters
	 * @param $submissionParameterName string the request parameter we expect
	 *  the submission id in.
	 */
	function QueryRequiredPolicy($request, &$args, $parameterName = 'queryId', $operations = null) {
		parent::DataObjectRequiredPolicy($request, $args, $parameterName, 'user.authorization.invalidQuery', $operations);
	}

	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see DataObjectRequiredPolicy::dataObjectEffect()
	 */
	function dataObjectEffect() {
		$queryId = (int)$this->getDataObjectId();
		if (!$queryId) return AUTHORIZATION_DENY;

		// Need a valid submission in request.
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		if (!is_a($submission, 'Submission')) return AUTHORIZATION_DENY;

		// Make sure the query belongs to the submission.
		$queryDao = DAORegistry::getDAO('QueryDAO');
		$query = $queryDao->getById($queryId, $submission->getId());
		if (!is_a($query, 'Query')) return AUTHORIZATION_DENY;

		// Save the query to the authorization context.
		$this->addAuthorizedContextObject(ASSOC_TYPE_QUERY, $query);
		return AUTHORIZATION_PERMIT;
	}
}

?>
