<?php

/**
 * @file api/v1/genres/resources/GenreResource.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GenreResource
 *
 * @ingroup api_v1_genres
 *
 * @brief Transforms a Genre object into the API response format.
 *
 */

namespace PKP\API\v1\genres\resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PKP\submission\Genre;

class GenreResource extends JsonResource
{
    /** @var Genre $resource */
    public $resource;
    public function toArray(Request $request = null): array
    {
        return [
            'id' => $this->resource->getId(),
            'contextId' => $this->resource->getContextId(),
            'name' => $this->resource->getData('name'),
            'key' => $this->resource->getKey(),
            'category' => $this->resource->getCategory(),
            'dependent' => (bool) $this->resource->getDependent(),
            'supplementary' => (bool) $this->resource->getSupplementary(),
            'required' => $this->resource->getRequired(),
            'supportsFileVariants' => $this->resource->getSupportsFileVariants(),
            'sequence' => $this->resource->getSequence(),
            'enabled' => (bool) $this->resource->getEnabled(),
            'isDefault' => $this->resource->isDefault(),
        ];
    }
}
