<?php

declare(strict_types=1);

/**
 * @file Domains/Jobs/Resources/JobResource.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class JobResource
 * @ingroup domains
 *
 * @brief Mapping class for CLI output values
 */

namespace PKP\Domains\Jobs\Resources;

use PKP\Domains\Jobs\Traits\JobResource;
use PKP\Support\Resources\BaseResource;

class CLIJobResource extends BaseResource
{
    use JobResource;

    /**
     * Transform the resource into an array.
     *
     */
    public function toArray(): array
    {
        $availableAt = isset($this->resource->available_at) ? $this->formatDate($this->resource->available_at) : '-';
        $createdAt = isset($this->resource->created_at) ? $this->formatDate($this->resource->created_at) : '-';
        $reservedAt = isset($this->resource->reserved_at) ? $this->formatDate($this->resource->reserved_at) : '-';

        return [
            'id' => $this->resource->id,
            'queue' => $this->resource->queue,
            'displayName' => $this->getJobName(),
            'attempts' => $this->resource->attempts,
            'reserved_at' => $reservedAt,
            'available_at' => $availableAt,
            'created_at' => $createdAt,
        ];
    }
}
