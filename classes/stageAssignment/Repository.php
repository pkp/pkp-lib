<?php

/**
 * @file classes/stageAssignment/Repository.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @ingroup stageAssignment
 *
 * @see StageAssignment
 *
 * @brief Operations for retrieving and modifying StageAssignment objects.
 */

namespace PKP\stageAssignment;

use APP\facades\Repo;
use PKP\core\Core;
use PKP\userGroup\UserGroup;

class Repository
{
    /**
     * Fetch a stageAssignment by symbolic info, building it if needed.
     *
     * @param int $submissionId
     * @param int $userGroupId
     * @param int $userId
     * @param bool $recommendOnly
     * @param bool $canChangeMetadata
     *
     * @return StageAssignment
     */
    public function build(int $submissionId, int $userGroupId, int $userId, ?bool $recommendOnly = null, ?bool $canChangeMetadata = null): StageAssignment
    {
        if (!isset($canChangeMetadata)) {
            /** @var UserGroup $userGroup */
            $userGroup = Repo::userGroup()->get($userGroupId);

            $canChangeMetadata = $userGroup->getPermitMetadataEdit();
        }

        if (!isset($recommendOnly)) {
            $recommendOnly = false;
        }

        return StageAssignment::withSubmissionIds([$submissionId])
            ->withUserId($userId)
            ->withUserGroupId($userGroupId)
            ->firstOr(function() use ($submissionId, $userGroupId, $userId, $recommendOnly, $canChangeMetadata) {
                return StageAssignment::create([
                    'submissionId' => $submissionId,
                    'userGroupId' => $userGroupId,
                    'userId' => $userId,
                    'recommendOnly' => $recommendOnly,
                    'canChangeMetadata' => $canChangeMetadata,
                    'dateAssigned' => Core::getCurrentDate(),
                ]);
            });
    }
}
