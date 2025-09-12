<?php

/**
 * @file api/v1/editTaskTemplates/resources/EditTaskTemplateResource.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditTaskTemplateResource
 *
 * @brief Transforms an editorial task template into an API response format.
 *
 */

namespace PKP\API\v1\editTaskTemplates\resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EditTaskTemplateResource extends JsonResource
{
    public function toArray(Request $request)
    {

        return [
            'id' => (int) $this->id,
            'stageId' => (int) $this->stage_id,
            'title' => $this->title,
            'include' => (bool) $this->include,
            'emailTemplateId' => $this->email_template_id ? (int) $this->email_template_id : null,
            'userGroupIds' => $this->whenLoaded('userGroups', fn () => $this->userGroups->pluck('user_group_id')->values()->all()),
            'userGroups' => $this->whenLoaded('userGroups', fn () => $this->userGroups->map(fn ($ug) => [
                'id' => (int) $ug->user_group_id,
                'name' => method_exists($ug, 'getLocalizedName') ? $ug->getLocalizedName() : ($ug->name ?? null),
            ])),
        ];
    }
}
