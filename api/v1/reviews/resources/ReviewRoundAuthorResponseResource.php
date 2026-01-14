<?php

/**
 * @file api/v1/reviews/resources/ReviewRoundAuthorResponseResource.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewRoundAuthorResponseResource
 *
 * @brief API resource class that maps a Review Round to its Author response
 *
 */

namespace PKP\API\v1\reviews\resources;

use APP\author\Author;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PKP\submission\reviewRound\ReviewAuthorResponse;
use PKP\user\User;

class ReviewRoundAuthorResponseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ReviewAuthorResponse $reponse */
        $response = $this;

        /** @var User $user */
        $user = $this->submittedBy;

        $associatedAuthors = $response->associatedAuthors;
        return [
            'reviewRoundId' => $response->reviewRoundId,
            'response' => $response->authorReviewResponse,
            'id' => $response->id,
            'submittedByUser' => [
                'id' => $user->getId(),
                'fullName' => $user->getFullName(),
            ],
            'associatedAuthors' => array_map(fn (Author $author) =>
                 [
                     'id' => $author->getId(),
                     'fullName' => $author->getFullName(),
                 ], $associatedAuthors),
        ];
    }
}
