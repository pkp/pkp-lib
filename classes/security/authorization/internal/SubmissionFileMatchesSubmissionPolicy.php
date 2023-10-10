<?php
/**
 * @file classes/security/authorization/internal/SubmissionFileMatchesSubmissionPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileMatchesSubmissionPolicy
 *
 * @ingroup security_authorization_internal
 *
 * @brief Submission file policy to check if the file belongs to the submission
 *
 * NB: This policy expects a previously authorized submission in the
 * authorization context.
 */

namespace PKP\security\authorization\internal;

use APP\core\Application;
use APP\submission\Submission;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\submissionFile\SubmissionFile;

class SubmissionFileMatchesSubmissionPolicy extends SubmissionFileBaseAccessPolicy
{
    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect()
    {
        // Get the submission file
        $request = $this->getRequest();
        $submissionFile = $this->getSubmissionFile($request);
        if (!$submissionFile instanceof SubmissionFile) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Get the submission
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        if (!$submission instanceof Submission) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }


        // Check if the submission file belongs to the submission.
        if ($submissionFile->getData('submissionId') == $submission->getId()) {
            // We add this submission file to the context submission files array.
            $submissionFilesArray = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION_FILES);
            if (is_null($submissionFilesArray)) {
                $submissionFilesArray = [];
            }
            array_push($submissionFilesArray, $submissionFile);
            $this->addAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION_FILES, $submissionFilesArray);

            // Save the submission file to the authorization context.
            $this->addAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION_FILE, $submissionFile);
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        } else {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\SubmissionFileMatchesSubmissionPolicy', '\SubmissionFileMatchesSubmissionPolicy');
}
