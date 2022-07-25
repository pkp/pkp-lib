<?php
/**
 * @file classes/decision/types/traits/ToNotifyReviewers.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief Helper functions to provide methods to notify reviewers when a decision taken
 */

namespace PKP\decision\types\traits;

use APP\submission\Submission;
use Illuminate\Validation\Validator;
use PKP\mail\EmailData;
use PKP\mail\Mailable;
use PKP\user\User;

trait ToNotifyReviewers
{
    protected string $ACTION_NOTIFY_REVIEWERS = 'notifyReviewers';

    /** @copydoc DecisionType::addEmailDataToMailable() */
    abstract protected function addEmailDataToMailable(Mailable $mailable, User $user, EmailData $email): Mailable;

    /** @copydoc DecisionType::getAssignedAuthorIds() */
    abstract protected function getAssignedAuthorIds(Submission $submission): array;

    /** @copydoc WithReviewAssignments::getReviewerIds() */
    abstract protected function getReviewerIds(int $submissionId, int $reviewRoundId, int $reviewAssignmentState): array;

    /** @copydoc DecisionType::setRecipientError() */
    abstract protected function setRecipientError(string $actionErrorKey, array $invalidRecipientIds, Validator $validator);

    /**
     * Validate the decision action to notify reviewers
     */
    protected function validateNotifyReviewersAction(array $action, string $actionErrorKey, Validator $validator, Submission $submission, int $reviewRoundId, string $reviewAssignmentStatus)
    {
        $errors = $this->validateEmailAction($action, $submission, $this->getAllowedAttachmentFileStages());

        foreach ($errors as $key => $propErrors) {
            foreach ($propErrors as $propError) {
                $validator->errors()->add($actionErrorKey . '.' . $key, $propError);
            }
        }

        if (empty($action['recipients'])) {
            $validator->errors()->add($actionErrorKey . '.recipients', __('validator.required'));
            return;
        }

        $reviewerIds = $this->getReviewerIds($submission->getId(), $reviewRoundId, $reviewAssignmentStatus);
        $invalidRecipients = array_diff($action['recipients'], $reviewerIds);

        if (count($invalidRecipients)) {
            $this->setRecipientError($actionErrorKey, $invalidRecipients, $validator);
        }
    }
}
