<?php

/**
 * @file api/v1/reviewers/suggestions/resources/ReviewerSuggestionResource.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerSuggestionResource
 *
 * @brief Transform the API response of reviewer suggestion in desired format
 *
 */

namespace PKP\API\v1\reviewers\suggestions\resources;

use APP\facades\Repo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewerSuggestionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request)
    {
        $suggestion = [
            'id' => $this->id,
            'submissionId' => $this->submissionId,
            'suggestingUserId' => $this->suggestingUserId,
            'familyName' => $this->familyName,
            'givenName' => $this->givenName,
            'fullName' => $this->fullname,
            'email' => $this->email,
            'orcidId' => $this->orcidId,
            'affiliation' => $this->affiliation,
            'suggestionReason' => $this->suggestionReason,
            'approvedAt' => $this->approvedAt,
            'existingUserId' => $this->existingUser?->getId(),
            'existingReviewerRole' => $this->existingReviewerRole,
            'reviewerId' => $this->reviewerId,
            
            // TODO :   should go with this approach? Didn't quite like it as `reviewer` will always
            //          present even when it's not asked for as `null` which seems misleading informtion.

            // 'reviewer' => $this->mergeWhen(
            //     $request->get('include_reviewer_data') && $this->reviewerId,
            //     fn () => Repo::user()->getSchemaMap()->summarizeReviewer($this->reviewer)
            // )?->data ?? null,
        ];

        if ($request->get('include_reviewer_data') && $this->reviewerId) {
            $suggestion['reviewer'] = Repo::user()->getSchemaMap()->summarizeReviewer($this->reviewer);
        }

        return $suggestion;
    }
}
