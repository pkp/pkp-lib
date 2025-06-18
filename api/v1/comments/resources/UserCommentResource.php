<?php

/**
 * @file api/v1/comments/resources/UserCommentResource.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2005 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserCommentResource
 *
 * @ingroup api_v1_comments
 *
 * @brief Class for mapping HTTP output values for user comments.
 */

namespace PKP\API\v1\comments\resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PKP\user\User;

class UserCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->user; /** @var User $user */
        $requestQueryParams = $request->query();

        $results = [
            'id' => $this->id,
            'contextId' => $this->contextId,
            'commentText' => $this->commentText,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'isApproved' => $this->isApproved,
            'isReported' => $this->reports->isNotEmpty(),
            'publicationId' => $this->publicationId,
            'userId' => $user->getId(),
            'userName' => $user->getFullName(),
            'userOrcidDisplayValue' => $user->getOrcidDisplayValue(),
        ];

        if (key_exists('includeReports', $requestQueryParams)) {
            $results['reports'] = UserCommentReportResource::collection($this->reports);
        }

        return $results;
    }
}
