<?php

/**
 * @file api/v1/editTaskTemplates/resources/TaskTemplateResource.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TaskTemplateResource
 *
 * @brief Transforms an editorial task template into an API response format.
 *
 */

namespace PKP\API\v1\editTaskTemplates\resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskTemplateResource extends JsonResource
{
    public function toArray(Request $request)
    {

        return [
            'id' => (int) $this->id,
            'type' => (int) $this->type,
            'stageId' => (int) $this->stageId,
            'title' => $this->title,
            'include' => (bool) $this->include,
            'dueInterval' => $this->dueInterval,
            'description' => $this->description,
            'restrictToUserGroups' => (bool) $this->restrictToUserGroups,
            'userGroups' => $this->whenLoaded(
                'userGroups',
                fn () => $this->userGroups
                    ->map(fn ($ug) => [
                        'id' => (int) $ug->userGroupId,
                        'name' => $ug->getLocalizedData('name'),
                    ])
                    ->values()
                    ->all()
            ),
        ];
    }
}
