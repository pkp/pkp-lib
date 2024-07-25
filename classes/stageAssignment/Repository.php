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

class Repository
{
    /**
     * Fetch a stageAssignment by symbolic info, building it if needed.
     */
    public function build(int $submissionId, int $userGroupId, int $userId, ?bool $recommendOnly = null, ?bool $canChangeMetadata = null): StageAssignment
    {
        // Set defaults
        $canChangeMetadata ??= Repo::userGroup()->get($userGroupId)->getPermitMetadataEdit();
        $recommendOnly ??= false;

        return StageAssignment::withSubmissionIds([$submissionId])
            ->withUserId($userId)
            ->withUserGroupId($userGroupId)
            ->firstOr(fn () => StageAssignment::create([
                'submissionId' => $submissionId,
                'userGroupId' => $userGroupId,
                'userId' => $userId,
                'recommendOnly' => $recommendOnly,
                'canChangeMetadata' => $canChangeMetadata,
                'dateAssigned' => Core::getCurrentDate(),
            ]));
    }
}
