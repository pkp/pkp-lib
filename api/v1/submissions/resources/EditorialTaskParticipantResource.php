<?php

/**
 * @file api/v1/reviewers/suggestions/resources/EditTaskParticipantResource.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditTaskParticipantResource
 *
 * @brief Transforms the API response of the editorial task participant into the desired format
 *
 */

namespace PKP\API\v1\submissions\resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EditorialTaskParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->userId,
            'isResponsible' => (bool) $this->isResponsible,
        ];
    }
}
