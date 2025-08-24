<?php

/**
 * @file api/v1/reviewers/suggestions/resources/TaskResource.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TaskResource
 *
 * @brief Transforms the API response of the editorial task and discussion into the desired format
 *
 */

namespace PKP\API\v1\submissions\resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PKP\user\User;

class TaskResource extends JsonResource
{
    use EnrichesWithData;

    public function toArray(Request $request, ?array $data = [])
    {
        if (!empty($data)) {
            $this->data = $data;
        }

        ['users' => $users] = $this->data;

        $createdBy = $users->get($this->createdBy); /** @var User $createdBy */

        return [
            'id' => $this->id,
            'type' => $this->type,
            'assocType' => $this->assocType,
            'assocId' => $this->assocId,
            'stageId' => $this->stageId,
            'status' => $this->status,
            'createdBy' => $this->createdBy,
            'createdByName' => $createdBy?->getFullName(),
            'createdByUsername' => $createdBy?->getUsername(),
            'dateDue' => $this->dateDue?->format('Y-m-d'),
            'dateStarted' => $this->dateStarted?->format('Y-m-d'),
            'dateClosed' => $this->dateClosed?->format('Y-m-d'),
            'title' => $this->title,
            'participants' => EditorialTaskParticipantResource::collection(resource: $this->participants, data: $this->data),
        ];
    }
}
