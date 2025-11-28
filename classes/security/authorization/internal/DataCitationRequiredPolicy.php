<?php
/**
 * @file classes/security/authorization/internal/DataCitationRequiredPolicy.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DataCitationRequiredPolicy
 *
 * @ingroup security_authorization_internal
 *
 * @brief Policy that ensures that the request contains a valid data citation.
 */

namespace PKP\security\authorization\internal;

use APP\core\Application;
use APP\publication\Publication;
use APP\submission\Submission;
use PKP\core\PKPRequest;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\security\authorization\DataObjectRequiredPolicy;
use PKP\dataCitation\DataCitation;


class DataCitationRequiredPolicy extends DataObjectRequiredPolicy
{
    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param array $args request parameters
     * @param null|mixed $operations
     */
    public function __construct($request, &$args, $parameterName = 'dataCitationId', $operations = null)
    {
        parent::__construct($request, $args, $parameterName, 'user.authorization.invalidDataCitation', $operations);
    }

    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see DataObjectRequiredPolicy::dataObjectEffect()
     */
    public function dataObjectEffect()
    {
        $dataCitationId = (int)$this->getDataObjectId();
        if (!$dataCitationId) {
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

        // Make sure the dataCitation belongs to the submission.
        $dataCitation = DataCitation::where('data_citation_id', $dataCitationId)
            ->where('publication_id', $publication->getId())
            ->first();
        if (!$dataCitation instanceof DataCitation) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Save the dataCitation to the authorization context.
        $this->addAuthorizedContextObject(Application::ASSOC_TYPE_DATA_CITATION, $dataCitation);
        return AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\DataCitationRequiredPolicy', '\DataCitationRequiredPolicy');
}
