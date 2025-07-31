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

class TaskResource extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'assocId' => $this->assocId,
            'stageId' => $this->stageId,
            'status' => $this->status,
            'createdBy' => $this->createdBy,
            'dateDue' => $this->dateDue->format('Y-m-d'),
            'participants' => EditorialTaskParticipantResource::collection($this->participants),
        ];
    }
}
