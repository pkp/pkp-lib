<?php

declare(strict_types=1);

/**
 * @file classes/job/resources/CLIJobResource.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CLIJobResource
 *
 * @brief Mapping class for CLI output values
 */

namespace PKP\job\resources;

use Illuminate\Http\Resources\Json\JsonResource;
use PKP\job\traits\JobResource;

class CLIJobResource extends JsonResource
{
    use JobResource;

    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->getResource()->id,
            'queue' => $this->getResource()->queue,
            'displayName' => $this->getJobName(),
            'attempts' => $this->getResource()->attempts,
            'reserved_at' => $this->getReservedAt(),
            'available_at' => $this->getAvailableAt(),
            'created_at' => $this->getCreatedAt(),
        ];
    }
}
