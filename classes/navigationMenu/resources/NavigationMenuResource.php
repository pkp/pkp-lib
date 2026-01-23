<?php

/**
 * @file classes/navigationMenu/resources/NavigationMenuResource.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuResource
 *
 * @brief Resource class for transforming NavigationMenu to API response format
 */

namespace PKP\navigationMenu\resources;

use Illuminate\Http\Resources\Json\JsonResource;
use PKP\navigationMenu\NavigationMenu;

class NavigationMenuResource extends JsonResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray($request): array
    {
        /** @var NavigationMenu $menu */
        $menu = $this->resource;

        return [
            'id' => $menu->getId(),
            'title' => $menu->getTitle(),
            'areaName' => $menu->getAreaName(),
            'contextId' => $menu->getContextId(),
        ];
    }
}
