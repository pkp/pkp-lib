<?php

namespace PKP\API\v1\reviewers\suggestions\resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewerSuggestionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request)
    {
        return [
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
        ];
    }
}
