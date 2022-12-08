<?php
/**
 * @file classes/security/authorization/internal/PublicationRequiredPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationRequiredPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Policy that ensures that the request contains a valid publication id.
 */

namespace PKP\security\authorization\internal;

use APP\core\Application;
use APP\facades\Repo;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\security\authorization\DataObjectRequiredPolicy;

class PublicationRequiredPolicy extends DataObjectRequiredPolicy
{
    protected ?string $publicationParameterName;

    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param array $args request parameters
     * @param ?string $publicationParameterName the request parameter we expect
     *  the submission id in. Pass null to look for the current publication in an authorized
     *   submission object instead.
     * @param null|mixed $operations
     */
    public function __construct($request, &$args, $publicationParameterName = null, $operations = null)
    {
        parent::__construct($request, $args, $publicationParameterName, 'user.authorization.invalidPublication', $operations);
        $this->publicationParameterName = $publicationParameterName;

        $callOnDeny = [$request->getDispatcher(), 'handle404', []];
        $this->setAdvice(
            AuthorizationPolicy::AUTHORIZATION_ADVICE_CALL_ON_DENY,
            $callOnDeny
        );
    }

    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see DataObjectRequiredPolicy::dataObjectEffect()
     */
    public function dataObjectEffect()
    {
        // Get the publication id from the policy or, if
        // no parameter name is passed, look for the current
        // publication in an authorized submission object
        if ($this->publicationParameterName) {
            $publication = Repo::publication()->get((int) $this->getDataObjectId());
        } else {
            $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
            if ($submission) {
                $publication = $submission->getCurrentPublication();
            }
        }

        if (!isset($publication)) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Save the publication to the authorization context.
        $this->addAuthorizedContextObject(Application::ASSOC_TYPE_PUBLICATION, $publication);
        return AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\PublicationRequiredPolicy', '\PublicationRequiredPolicy');
}
