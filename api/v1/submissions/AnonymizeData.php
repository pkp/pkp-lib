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
use Illuminate\Support\Collection;
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
     * @return false|Collection List of review IDs to anonymize or false;
     */
    public function anonymizeReviews(LazyCollection|Submission $submissions, ?LazyCollection $reviewAssignments = null): false|Collection
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

        $currentUserGroups = Repo::userGroup()->getCollector()
            ->filterByUserGroupIds($currentUserUserGroupIds)
            ->getMany();

        $isAuthor = $currentUserGroups->contains(
            fn (UserGroup $userGroup) =>
            $userGroup->getRoleId() == Role::ROLE_ID_AUTHOR
        );

        if ($currentUserReviewAssignment->isNotEmpty() || $isAuthor) {
            $anonymizeReviews = $reviewAssignments->map(function (ReviewAssignment $reviewAssignment, int $reviewId) use ($currentUserReviewAssignment) {
                if ($currentUserReviewAssignment->isNotEmpty() && $currentUserReviewAssignment->has($reviewId)) {
                    return false;
                }
                return $reviewAssignment->getReviewMethod() !== ReviewAssignment::SUBMISSION_REVIEW_METHOD_OPEN;
            })->filter()->keys()->collect();
        }

        return !isset($anonymizeReviews) || $anonymizeReviews->isEmpty() ? false : $anonymizeReviews;
    }
}
