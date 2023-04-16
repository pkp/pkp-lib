<?php
/**
 * @file classes/security/authorization/internal/SubmissionFileAssignedQueryAccessPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileAssignedQueryAccessPolicy
 *
 * @ingroup security_authorization_internal
 *
 * @brief Submission file policy to check if the current user is a participant
 * 	in a query the file belongs to.
 *
 */

namespace PKP\security\authorization\internal;

use APP\core\Application;
use PKP\db\DAORegistry;
use PKP\security\authorization\AuthorizationPolicy;

class SubmissionFileAssignedQueryAccessPolicy extends SubmissionFileBaseAccessPolicy
{
    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect()
    {
        $request = $this->getRequest();

        // Get the user
        $user = $request->getUser();
        if (!$user instanceof \PKP\user\User) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Get the submission file
        $submissionFile = $this->getSubmissionFile($request);
        if (!$submissionFile instanceof \PKP\submissionFile\SubmissionFile) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Check if it's associated with a note.
        if ($submissionFile->getData('assocType') != Application::ASSOC_TYPE_NOTE) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        $noteDao = DAORegistry::getDAO('NoteDAO'); /** @var NoteDAO $noteDao */
        $note = $noteDao->getById($submissionFile->getData('assocId'));
        if (!$note instanceof \PKP\note\Note) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        if ($note->getAssocType() != Application::ASSOC_TYPE_QUERY) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }
        $queryDao = DAORegistry::getDAO('QueryDAO'); /** @var QueryDAO $queryDao */
        $query = $queryDao->getById($note->getAssocId());
        if (!$query) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        if ($queryDao->getParticipantIds($note->getAssocId(), $user->getId())) {
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        }

        return AuthorizationPolicy::AUTHORIZATION_DENY;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\SubmissionFileAssignedQueryAccessPolicy', '\SubmissionFileAssignedQueryAccessPolicy');
}
