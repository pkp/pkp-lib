<?php

/**
 * @file classes/security/authorization/internal/SubmissionFileUploaderAccessPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileUploaderAccessPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Submission file policy to check if the current user is the uploader.
 *
 */

namespace PKP\security\authorization\internal;

use PKP\security\authorization\AuthorizationPolicy;

class SubmissionFileUploaderAccessPolicy extends SubmissionFileBaseAccessPolicy
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

        // Check if the uploader is the current user.
        if ($submissionFile->getUploaderUserId() == $user->getId()) {
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        } else {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\SubmissionFileUploaderAccessPolicy', '\SubmissionFileUploaderAccessPolicy');
}
