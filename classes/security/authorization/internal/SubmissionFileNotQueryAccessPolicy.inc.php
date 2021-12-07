<?php
/**
 * @file classes/security/authorization/internal/SubmissionFileNotQueryAccessPolicy.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileNotQueryAccessPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Submission file policy to check if the requested file is not attached
 *  to a query. This returns AUTHORIZATION_PERMIT for _any_ file that is not
 *  attached to a query note.
 */

namespace PKP\security\authorization\internal;

use PKP\db\DAORegistry;
use PKP\security\authorization\AuthorizationPolicy;

class SubmissionFileNotQueryAccessPolicy extends SubmissionFileBaseAccessPolicy
{
    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect()
    {
        $request = $this->getRequest();

        // Get the submission file
        $submissionFile = $this->getSubmissionFile($request);
        if (!$submissionFile instanceof \PKP\submissionFile\SubmissionFile) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Check if it's associated with a note.
        if ($submissionFile->getData('assocType') != ASSOC_TYPE_NOTE) {
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        }

        // Check if that note is associated with a query
        $noteDao = DAORegistry::getDAO('NoteDAO'); /** @var NoteDAO $noteDao */
        $note = $noteDao->getById($submissionFile->getData('assocId'));
        if ($note->getAssocType() != ASSOC_TYPE_QUERY) {
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        }

        return AuthorizationPolicy::AUTHORIZATION_DENY;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\SubmissionFileNotQueryAccessPolicy', '\SubmissionFileNotQueryAccessPolicy');
}
