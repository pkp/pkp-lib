<?php
/**
 * @file classes/security/authorization/internal/QueryRequiredPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QueryRequiredPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Policy that ensures that the request contains a valid query.
 */

namespace PKP\security\authorization\internal;

use APP\submission\Submission;
use PKP\db\DAORegistry;
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
        $queryDao = DAORegistry::getDAO('QueryDAO'); /** @var QueryDAO $queryDao */
        $query = $queryDao->getById($queryId);
        if (!$query instanceof Query) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }
        switch ($query->getAssocType()) {
            case ASSOC_TYPE_SUBMISSION:
                $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
                if (!$submission instanceof Submission) {
                    return AuthorizationPolicy::AUTHORIZATION_DENY;
                }
                if ($query->getAssocId() != $submission->getId()) {
                    return AuthorizationPolicy::AUTHORIZATION_DENY;
                }
                break;
            default:
                return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Save the query to the authorization context.
        $this->addAuthorizedContextObject(ASSOC_TYPE_QUERY, $query);
        return AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\QueryRequiredPolicy', '\QueryRequiredPolicy');
}
