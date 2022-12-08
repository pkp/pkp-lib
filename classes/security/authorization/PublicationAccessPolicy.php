<?php
/**
 * @file classes/security/authorization/PublicationAccessPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationAccessPolicy
 * @ingroup security_authorization
 *
 * @brief Class to control access to a publication
 */

namespace PKP\security\authorization;

use PKP\security\authorization\internal\ContextPolicy;
use PKP\security\authorization\internal\PublicationIsSubmissionPolicy;
use PKP\security\authorization\internal\PublicationRequiredPolicy;

class PublicationAccessPolicy extends ContextPolicy
{
    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param array $args request parameters
     * @param array $roleAssignments
     */
    public function __construct($request, $args, $roleAssignments, $submissionIdParameter = 'submissionId', $publicationIdParameter = 'publicationId')
    {
        parent::__construct($request);

        // Can the user access this submission?
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments, $submissionIdParameter));

        // Does the publication exist?
        $this->addPolicy(new PublicationRequiredPolicy($request, $args, $publicationIdParameter));

        // Is the publication attached to the correct submission?
        $this->addPolicy(new PublicationIsSubmissionPolicy(__('api.publications.403.submissionsDidNotMatch')));
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\PublicationAccessPolicy', '\PublicationAccessPolicy');
}
