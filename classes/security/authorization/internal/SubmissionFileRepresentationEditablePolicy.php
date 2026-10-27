<?php

/**
 * @file classes/security/authorization/internal/SubmissionFileRepresentationEditablePolicy.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileRepresentationEditablePolicy
 *
 * @ingroup security_authorization_internal
 *
 * @brief Submission file policy to check that the file of a representation (galley,
 *   publication format), or a file depending on it, may be changed by the current
 *   user. Files that are not attached to a representation are not affected.
 */

namespace PKP\security\authorization\internal;

use APP\core\Application;
use APP\facades\Repo;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\submissionFile\SubmissionFile;
use PKP\user\User;

class SubmissionFileRepresentationEditablePolicy extends SubmissionFileBaseAccessPolicy
{
    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect(): int
    {
        $request = $this->getRequest();

        $user = $request->getUser();
        if (!$user instanceof User) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        $submissionFile = $this->getSubmissionFile($request);
        if (!$submissionFile instanceof SubmissionFile) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // A dependent file (e.g. an image of an HTML galley) follows the file it belongs to
        if ($submissionFile->getData('fileStage') == SubmissionFile::SUBMISSION_FILE_DEPENDENT
            && $submissionFile->getData('assocType') == Application::ASSOC_TYPE_SUBMISSION_FILE) {
            $submissionFile = Repo::submissionFile()->get((int) $submissionFile->getData('assocId'));
            if (!$submissionFile) {
                return AuthorizationPolicy::AUTHORIZATION_DENY;
            }
        }

        if ($submissionFile->getData('assocType') != Application::ASSOC_TYPE_REPRESENTATION) {
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        }

        $representation = Application::getRepresentationDAO()->getById((int) $submissionFile->getData('assocId'));
        $publication = $representation ? Repo::publication()->get((int) $representation->getData('publicationId')) : null;
        if (!$publication || $publication->getData('submissionId') != $submissionFile->getData('submissionId')) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        return Repo::submission()->canEditRepresentations($publication, $user)
            ? AuthorizationPolicy::AUTHORIZATION_PERMIT
            : AuthorizationPolicy::AUTHORIZATION_DENY;
    }
}
