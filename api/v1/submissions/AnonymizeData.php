<?php

/**
 * @file api/v1/submissions/AnonymizeData.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AnonymizeData
 *
 * @ingroup api_v1_submission
 *
 * @brief Trait for anonymizing sensitive submission data.
 *
 */

namespace PKP\API\v1\submissions;

use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Support\LazyCollection;
use PKP\core\PKPRequest;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\userGroup\UserGroup;

trait AnonymizeData
{
    abstract public function getRequest(): PKPRequest;

    /**
     * Checks if sensitive review assignment data should be anonymized for authors and reviewers
     *
     * @param LazyCollection<Submission>|Submission $submissions the list of submissions with IDs as keys or a single submission
     * @param ?LazyCollection<ReviewAssignment> $reviewAssignments
     *
     * @return int[] List of review IDs to anonymize
     */
    public function reviewsToAnonymize(LazyCollection|Submission $submissions, ?LazyCollection $reviewAssignments = null): array
    {
        $currentUser = $this->getRequest()->getUser();
        $submissionIds = is_a($submissions, Submission::class) ? [$submissions->getId()] : $submissions->keys()->toArray();
        $reviewAssignments = $reviewAssignments ?? Repo::reviewAssignment()->getCollector()->filterBySubmissionIds($submissionIds)->getMany();

        $currentUserReviewAssignment = Repo::reviewAssignment()->getCollector()
            ->filterBySubmissionIds($submissionIds)
            ->filterByReviewerIds([$currentUser->getId()])
            ->getMany();

        $currentUserUserGroupIds = StageAssignment::withSubmissionIds($submissionIds)
            ->withUserId($currentUser->getId())
            ->pluck('user_group_id')
            ->toArray();

        $currentUserGroups = UserGroup::withUserGroupIds($currentUserUserGroupIds)->get();


        $isAuthor = $currentUserGroups->contains(
            fn (UserGroup $userGroup) =>
            $userGroup->roleId == Role::ROLE_ID_AUTHOR
        );

        if ($currentUserReviewAssignment->isNotEmpty() || $isAuthor) {
            $reviewsToAnonymize = $reviewAssignments->map(function (ReviewAssignment $reviewAssignment, int $reviewId) use ($currentUserReviewAssignment) {
                if ($currentUserReviewAssignment->isNotEmpty() && $currentUserReviewAssignment->has($reviewId)) {
                    return false;
                }
                return $reviewAssignment->getReviewMethod() !== ReviewAssignment::SUBMISSION_REVIEW_METHOD_OPEN;
            })->filter()->keys()->toArray();
        }

        return $reviewsToAnonymize ?? [];
    }

    /**
     * Checks if sensitive author data should be anonymized for reviewers
     *
     * @param LazyCollection<Submission>|Submission $submissions the list of submissions with IDs as keys or a single submission
     * @param ?LazyCollection<ReviewAssignment> $reviewAssignments
     *
     * @return int[] List of submission that should exclude author data
     */
    public function submissionsToAnonymizeByAuthor(LazyCollection|Submission $submissions, ?LazyCollection $reviewAssignments = null): array
    {
        $currentUser = $this->getRequest()->getUser();
        $submissionIds = is_a($submissions, Submission::class) ? [$submissions->getId()] : $submissions->keys()->toArray();
        $reviewAssignments = $reviewAssignments ?? Repo::reviewAssignment()->getCollector()->filterBySubmissionIds($submissionIds)->getMany();

        $submissionsToAnonymizeByAuthor = $reviewAssignments->filter(function (ReviewAssignment $reviewAssignment) use ($currentUser) {
            if ($reviewAssignment->getReviewerId() !== $currentUser->getId()) {
                return false;
            }

            // If the current user is a reviewer, we do not need to anonymize authors if the review type allows it.
            return !in_array(
                $reviewAssignment->getReviewMethod(),
                [
                    ReviewAssignment::SUBMISSION_REVIEW_METHOD_ANONYMOUS,
                    ReviewAssignment::SUBMISSION_REVIEW_METHOD_OPEN
                ]
            );
        })
            ->map(fn (ReviewAssignment $reviewAssignment) => $reviewAssignment->getSubmissionId())
            ->values()
            ->toArray();

        return $submissionsToAnonymizeByAuthor ?? [];
    }
}
