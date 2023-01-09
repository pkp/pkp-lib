<?php

declare(strict_types=1);

/**
 * @file classes/job/resources/CLIFailedJobResource.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CLIFailedJobResource
 *
 * @brief Mapping class for CLI output values
 */

namespace PKP\job\resources;

use PKP\job\resources\BaseResource;
use PKP\job\traits\JobResource;

class CLIFailedJobResource extends BaseResource
{
    use JobResource;

    /**
     * Transform the resource into an array.
     */
    public function toArray(): array
    {
        return [
            'id'            => $this->getResource()->id,
            'queue'         => $this->getResource()->queue,
            'displayName'   => $this->getJobName(),
            'connection'    => $this->getResource()->connection,
            'failed_at'     => $this->getFailedAt(),
            'exception'     => chunk_split($this->getResource()->exceptionMessage(), 50),
        ];
    }
}
