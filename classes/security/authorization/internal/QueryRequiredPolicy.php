<?php
/**
 * @file classes/security/authorization/internal/QueryRequiredPolicy.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QueryRequiredPolicy
 *
 * @ingroup security_authorization_internal
 *
 * @brief Policy that ensures that the request contains a valid query.
 */

namespace PKP\security\authorization\internal;

use APP\core\Application;
use APP\submission\Submission;
use PKP\core\PKPRequest;
use PKP\query\Query;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\security\authorization\DataObjectRequiredPolicy;

class QueryRequiredPolicy extends DataObjectRequiredPolicy
{
    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param array $args request parameters
     * @param null|mixed $operations
     */
    public function __construct($request, &$args, $parameterName = 'queryId', $operations = null)
    {
        parent::__construct($request, $args, $parameterName, 'user.authorization.invalidQuery', $operations);
    }

    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see DataObjectRequiredPolicy::dataObjectEffect()
     */
    public function dataObjectEffect()
    {
        $queryId = (int)$this->getDataObjectId();
        if (!$queryId) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Make sure the query belongs to the submission.
        $query = Query::find($queryId);
        if (!$query instanceof Query) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }
        switch ($query->assocType) {
            case Application::ASSOC_TYPE_SUBMISSION:
                $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
                if (!$submission instanceof Submission) {
                    return AuthorizationPolicy::AUTHORIZATION_DENY;
                }
                if ($query->assocId != $submission->getId()) {
                    return AuthorizationPolicy::AUTHORIZATION_DENY;
                }
                break;
            default:
                return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Save the query to the authorization context.
        $this->addAuthorizedContextObject(Application::ASSOC_TYPE_QUERY, $query);
        return AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }
}
