<?php

/**
 * @file api/v1/comments/resources/UserCommentReportResource.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2005 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserCommentReportResource
 *
 * @ingroup api_v1_comments
 *
 * @brief Class for mapping HTTP output values for user comment reports.
 */

namespace PKP\API\v1\comments\resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PKP\user\User;

class UserCommentReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->user;

        return [
            'id' => $this->id,
            'userCommentId' => $this->userCommentId,
            'userId' => $user->getId(),
            'userName' => $user->getFullName(),
            'userOrcidDisplayValue' => $user->getOrcidDisplayValue(),
            'note' => $this->note,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
