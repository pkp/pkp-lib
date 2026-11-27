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
use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PKP\facades\Locale;
use PKP\security\Role;
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
        $reviewRound = $response->reviewRound;
        $loggedInUser = Application::get()->getRequest()->getUser();
        $context = Application::get()->getRequest()->getContext();
        $canAccessEditorialData = $loggedInUser && $reviewRound->canUserAccessAuthorResponses($loggedInUser, [Role::ROLE_ID_SUB_EDITOR], $context);
        $isAssignedAuthor = $loggedInUser && $reviewRound->canUserAccessAuthorResponses($loggedInUser, [Role::ROLE_ID_AUTHOR], $context);

        return [
            'id' => $response->id,
            'reviewRoundId' => $response->reviewRoundId,
            'response' => $response->authorResponse,
            'associatedAuthors' => array_map(fn (Author $author) => [
                'id' => $author->getId(),
                'fullName' => $author->getFullName(),
                'givenName' => $author->getLocalizedGivenName(),
                'familyName' => $author->getLocalizedFamilyName(),
                'preferredPublicName' => $author->getPreferredPublicName(Locale::getLocale()),
                'orcid' => $author->getOrcid(),
                'hasVerifiedOrcid' => (bool)$author->hasVerifiedOrcid(),
            ], array_values($associatedAuthors)),
            'submittedByUser' => [
                'id' => $user->getId(),
                'fullName' => $user->getFullName(),
            ],
            'isPublic' => $response->isPublic,
            'createdAt' => $response->created_at?->format('Y-m-d H:i:s'),
            $this->mergeWhen($canAccessEditorialData, [
                'dateReverted' => $response->dateReverted?->format('Y-m-d H:i:s'),
                'confirmationChangedByUser' => $response->confirmationChangedByUserId ? $this->getUserInfo($response->confirmationChangedByUserId) : null,
                'lastModifiedByUser' => $response->lastModifiedById ? $this->getUserInfo($response->lastModifiedById) : null,
            ]),
            $this->mergeWhen($isAssignedAuthor || $canAccessEditorialData, [
                'dateConfirmed' => $response->dateConfirmed?->format('Y-m-d H:i:s'),
            ])
        ];
    }

    /**
     * Get user info for a given user ID.
     */
    private function getUserInfo(int $userId): ?array
    {
        $user = Repo::user()->get($userId, true);

        if (!$user) {
            return null;
        }

        return [
            'userId' => $user->getId(),
            'fullName' => $user->getFullName(),
        ];
    }
}
