<?php

/**
 * @file api/v1/reviewers/suggestions/resources/EditTaskParticipantResource.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditTaskParticipantResource
 *
 * @brief Transforms the API response of the editorial task participant into the desired format
 *
 */

namespace PKP\API\v1\submissions\tasks\resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PKP\core\traits\ResourceWithData;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\user\User;

class EditorialTaskParticipantResource extends JsonResource
{
    use ResourceWithData;

    public function toArray(Request $request, array $data = []): array
    {
        [$stageAssignments, $users, $userGroups, $reviewAssignments] = $this->getData('stageAssignments', 'users', 'userGroups', 'reviewAssignments');

        $user = $users->first(fn (User $user) => $user->getId() === $this->userId);
        $userAssignments = $stageAssignments->where('userId', $this->userId);

        // Identify participant roles
        $roles = [];

        // Check stage assignment roles
        if ($userAssignments->isNotEmpty()) {
            $userAssignments->each(function (StageAssignment $stageAssignment) use (&$roles) {
                $roles[$stageAssignment->userGroup->roleId] = $stageAssignment->userGroup->getLocalizedData('name');
            });
        }

        // Determine global roles
        $reviewerRoleName = null;
        foreach ($userGroups as $userGroup) {

            // Get the name for the reviewer role if it exists
            if (
                $reviewAssignments->isNotEmpty() &&
                $userGroup->roleId === Role::ROLE_ID_REVIEWER &&
                is_null($reviewerRoleName)
            ) {
                $reviewerRoleName = $userGroup->getLocalizedData('name');
            }

            // Check only global roles
            if (!in_array($userGroup->roleId, [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN])) {
                continue;
            }

            foreach ($userGroup->userUserGroups as $userUserGroup) {
                // Ensure the right user
                if ($userUserGroup->userId !== $this->userId) {
                    continue;
                }

                // We might already record this role if user has stage assignment
                if (array_key_exists($userGroup->roleId, $roles)) {
                    continue;
                }

                // Finally, record the role
                $roles[$userGroup->roleId] = $userGroup->getLocalizedData('name');
            }
        }

        $reviewMethod = null;
        if ($reviewAssignments->isNotEmpty()) {
            $reviewAssignmentsForUser = $reviewAssignments->first(fn (ReviewAssignment $reviewAssignment) => $reviewAssignment->getReviewerId() == $this->userId);
            if ($reviewAssignmentsForUser) {
                $roles[Role::ROLE_ID_REVIEWER] = $reviewerRoleName;
                $reviewMethods = $reviewAssignments->map(fn (ReviewAssignment $reviewAssignment) => $reviewAssignment->getReviewMethod())->unique();
                $reviewMethod = $reviewMethods->first(fn () => ReviewAssignment::SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS) ??
                    $reviewMethods->first(fn () => ReviewAssignment::SUBMISSION_REVIEW_METHOD_ANONYMOUS) ??
                    $reviewMethods->firstWhere(fn () => ReviewAssignment::SUBMISSION_REVIEW_METHOD_OPEN);
            }
        }

        $groupedRoles = [];
        foreach ($roles as $roleId => $roleName) {
            $role = [
                'id' => $roleId,
                'name' => $roleName,
            ];

            if ($roleId == Role::ROLE_ID_REVIEWER) {
                $role['reviewMethod'] = $reviewMethod;
            }
            $groupedRoles[] = $role;
        }

        return [
            'id' => $this?->id,
            'userId' => $this->userId,
            'isResponsible' => (bool) $this->isResponsible,
            'fullName' => $user->getFullName(),
            'username' => $user->getUsername(),
            'roles' => $groupedRoles,
        ];
    }

    /**
     * @inheritDoc
     */
    protected static function requiredKeys(): array
    {
        return [
            'submission',
            'users',
            'userGroups',
            'stageAssignments',
            'reviewAssignments',
        ];
    }
}
