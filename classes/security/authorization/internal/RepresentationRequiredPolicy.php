<?php
/**
 * @file classes/security/authorization/internal/RepresentationRequiredPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RepresentationRequiredPolicy
 *
 * @ingroup security_authorization_internal
 *
 * @brief Policy that ensures that the request contains a valid representation.
 */

namespace PKP\security\authorization\internal;

use APP\core\Application;
use APP\publication\Publication;
use APP\submission\Submission;

use PKP\security\authorization\AuthorizationPolicy;
use PKP\security\authorization\DataObjectRequiredPolicy;
use PKP\submission\Representation;

class RepresentationRequiredPolicy extends DataObjectRequiredPolicy
{
    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param array $args request parameters
     * @param null|mixed $operations
     */
    public function __construct($request, &$args, $parameterName = 'representationId', $operations = null)
    {
        parent::__construct($request, $args, $parameterName, 'user.authorization.invalidRepresentation', $operations);
    }

    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see DataObjectRequiredPolicy::dataObjectEffect()
     */
    public function dataObjectEffect()
    {
        $representationId = (int)$this->getDataObjectId();
        if (!$representationId) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Need a valid submission in request.
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        if (!$submission instanceof Submission) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Need a valid publication in request
        $publication = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_PUBLICATION);
        if (!$publication instanceof Publication) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Make sure the representation belongs to the submission.
        $representationDao = Application::getRepresentationDAO();
        $representation = $representationDao->getById($representationId, $publication->getId());
        if (!$representation instanceof Representation) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Save the representation to the authorization context.
        $this->addAuthorizedContextObject(Application::ASSOC_TYPE_REPRESENTATION, $representation);
        return AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\RepresentationRequiredPolicy', '\RepresentationRequiredPolicy');
}
