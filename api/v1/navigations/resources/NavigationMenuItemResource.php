<?php

/**
 * @file api/v1/navigations/resources/NavigationMenuItemResource.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuItemResource
 *
 * @brief Transforms the API response of navigation menu items into the desired format
 *
 */

namespace PKP\API\v1\navigations\resources;

use APP\core\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PKP\core\traits\ResourceWithData;
use PKP\facades\Locale;

class NavigationMenuItemResource extends JsonResource
{
    use ResourceWithData;

    public function toArray(Request $request): array
    {
        $context = Application::get()->getRequest()->getContext();
        $supportedLocales = $context ? $context->getSupportedLocales() : [];

        $titleData = [];
        if ($this->titleLocaleKey) {
            foreach ($supportedLocales as $locale) {
                $titleData[$locale] = Locale::get($this->titleLocaleKey, [], $locale);
            }
        }

        return [
            'id' => $this->id,
            'path' => $this->path,
            'type' => $this->type,
            'title' => $titleData
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
