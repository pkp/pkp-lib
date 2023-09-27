<?php
/**
 * @file classes/core/resources/DataObjectResource.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DataObjectResource
 *
 * @brief Map a data object to their properties
 */

namespace PKP\core\resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DataObjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $customObjectArray = (array) $this->getAllData();

        return $customObjectArray;
    }
}
