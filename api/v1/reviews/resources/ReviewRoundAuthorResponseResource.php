<?php

/**
 * @file api/v1/reviews/resources/ReviewRoundAuthorResponseResource.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewRoundAuthorResponseResource
 *
 * @brief API resource class that maps a ReviewRoundAuthorResponse to API response.
 *
 */

namespace PKP\API\v1\reviews\resources;

use APP\author\Author;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PKP\submission\reviewRound\authorResponse\AuthorResponse;
use PKP\user\User;

class ReviewRoundAuthorResponseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var AuthorResponse $response */
        $response = $this;

        /** @var User $user */
        $user = $this->submittedBy;

        $associatedAuthors = $response->associatedAuthors;
        return [
            'id' => $response->id,
            'reviewRoundId' => $response->reviewRoundId,
            'response' => $response->authorResponse,
            'associatedAuthors' => array_map(fn (Author $author) => [
                'id' => $author->getId(),
                'fullName' => $author->getFullName(),
            ], $associatedAuthors),
            'submittedByUser' => [
                'id' => $user->getId(),
                'fullName' => $user->getFullName(),
            ],
            'isPublic' => $response->isPublic,
            'createdAt' => $response->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
