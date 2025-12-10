<?php

/**
 * @file api/v1/context/resources/UserGroupResource.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserGroupResource
 *
 * @brief Transforms the API response of the note into the desired format
 *
 */

namespace PKP\API\v1\contexts\resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserGroupResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'roleId' => $this->roleId,
            'isDefault' => (bool) $this->is_default,
            'name' => $this->getLocalizedData('name'),
        ];
    }
}
