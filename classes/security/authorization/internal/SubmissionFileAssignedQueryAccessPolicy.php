<?php
/**
 * @file classes/security/authorization/internal/SubmissionFileAssignedQueryAccessPolicy.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
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
use PKP\note\Note;
use PKP\query\Query;
use PKP\query\QueryParticipant;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\submissionFile\SubmissionFile;
use PKP\user\User;

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
        if (!$user instanceof User) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Get the submission file
        $submissionFile = $this->getSubmissionFile($request);
        if (!$submissionFile instanceof SubmissionFile) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Check if it's associated with a note.
        if ($submissionFile->getData('assocType') != Application::ASSOC_TYPE_NOTE) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        $note = Note::find($submissionFile->getData('assocId'));
        if (!$note instanceof Note) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        if ($note->assocType != Application::ASSOC_TYPE_QUERY) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }
        $query = Query::find($note->assocId);
        if (!$query) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        $participantIds = QueryParticipant::withQueryId($query->id)
            ->pluck('user_id')
            ->all();
        if (in_array($user->getId(), $participantIds)) {
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        }

        return AuthorizationPolicy::AUTHORIZATION_DENY;
    }
}
