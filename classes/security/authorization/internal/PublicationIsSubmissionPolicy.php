<?php
/**
 * @file classes/security/authorization/internal/PublicationIsSubmissionPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationIsSubmissionPolicy
 *
 * @ingroup security_authorization_internal
 *
 * @brief Policy to ensure the authorized publication is related to the authorized submission.
 *
 */

namespace PKP\security\authorization\internal;

use APP\core\Application;
use PKP\security\authorization\AuthorizationPolicy;

class PublicationIsSubmissionPolicy extends AuthorizationPolicy
{
    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect()
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $publication = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_PUBLICATION);

        if ($submission && $publication && $submission->getId() === $publication->getData('submissionId')) {
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        }

        return AuthorizationPolicy::AUTHORIZATION_DENY;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\PublicationIsSubmissionPolicy', '\PublicationIsSubmissionPolicy');
}
