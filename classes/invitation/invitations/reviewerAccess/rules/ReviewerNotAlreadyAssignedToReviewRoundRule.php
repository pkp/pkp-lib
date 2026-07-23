<?php

namespace PKP\invitation\invitations\reviewerAccess\rules;

use APP\facades\Repo;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ReviewerNotAlreadyAssignedToReviewRoundRule implements ValidationRule
{
    private int $submissionId;
    private int $reviewRoundId;
    public function __construct(
        int $submissionId,
        int $reviewRoundId
    ) {
        $this->submissionId = $submissionId;
        $this->reviewRoundId = $reviewRoundId;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        /// Allow null/empty through
        if ($value === null || $value === '') {
            return;
        }

        $userId = filter_var($value, FILTER_VALIDATE_INT);
        if ($userId === false) {
            // Let other rules (integer/exists) handle this if you want.
            $fail(__('validation.integer'));
            return;
        }

        // Goal: check if this user is already assigned as reviewer in the same review round.
        $alreadyAssigned = Repo::reviewAssignment()
                ->getCollector()
                ->filterBySubmissionIds([$this->submissionId])
                ->filterByReviewRoundIds([$this->reviewRoundId])
                ->filterByReviewerIds([$userId])
                ->getCount() > 0;

        if ($alreadyAssigned) {
            $fail(__('reviewer.reviewAssignmentInvite.validation.error.user.alreadyAssignedToReview'));
        }
    }
}
