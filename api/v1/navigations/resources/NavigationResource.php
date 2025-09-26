<?php

/**
 * @file api/v1/navigations/resources/NavigationResource.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationResource
 *
 * @brief Transforms the API response of the navigation menu into the desired format
 *
 */

namespace PKP\API\v1\navigations\resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PKP\core\traits\ResourceWithData;

class NavigationResource extends JsonResource
{
    use ResourceWithData;

    public function toArray(Request $request): array
    {

        return [
            'id' => $this->id,
            'title' => $this->title,
            'area_name' => $this->area_name,
            'context_id' => $this->context_id,
            'items' => NavigationMenuItemResource::collection($this->menuItems ?? collect(), $this->data ?? []),
        ];
    }

    /**
     * @inheritDoc
     */
    protected static function requiredKeys(): array
    {
        return [];
    }
}
