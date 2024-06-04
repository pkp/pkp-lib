<?php

/**
 * @file classes/security/authorization/internal/SubmissionCompletePolicy.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionCompletePolicy
 *
 * @ingroup security_authorization_internal
 *
 * @brief Class to control access to workflow only for complete submissions
 * 
 */

namespace PKP\security\authorization\internal;

use APP\facades\Repo;
use PKP\security\authorization\DataObjectRequiredPolicy;
use PKP\core\PKPRequest;
use PKP\security\authorization\AuthorizationPolicy;

class SubmissionCompletePolicy extends DataObjectRequiredPolicy
{
    /**
     * Constructor
     * 
     * @param PKPRequest 	$request 					The PKP core request object
     * @param array 		$args 						Request parameters
     * @param string 		$submissionParameterName 	The request parameter we expect the submission id in.
     * @param mixed|null 	$operation 					Optional list of operations for which this check takes effect. If specified, 
     * 													operations outside this set will not be checked against this policy
     */
    function __construct(PKPRequest $request, array &$args, string $submissionParameterName = 'submissionId', mixed $operations = null) {
        parent::__construct(
            $request,
            $args,
            $submissionParameterName,
            'user.authorization.submission.incomplete.workflowAccessRestrict',
            $operations
        );
    }


    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see DataObjectRequiredPolicy::dataObjectEffect()
     */
    public function dataObjectEffect() {
        $submissionId = $this->getDataObjectId();

        $submission = Repo::submission()->get((int) $submissionId);

        if ($submission->getData('submissionProgress') > 0) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        return AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }
}
