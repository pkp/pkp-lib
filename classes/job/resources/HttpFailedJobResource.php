<?php

declare(strict_types=1);

/**
 * @file classes/job/resources/HttpFailedJobResource.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HttpFailedJobResource
 *
 * @brief Mapping class for HTTP Output values
 */

namespace PKP\job\resources;

use PKP\job\resources\BaseResource;
use PKP\job\traits\JobResource;

class HttpFailedJobResource extends BaseResource
{
    use JobResource;

    /**
     * Transform the resource into an array.
     */
    public function toArray(): array
    {
        return [
            'id'            => $this->getResource()->id,
            'displayName'   => $this->getJobName(),
            'queue'         => $this->getResource()->queue,
            'connection'    => $this->getResource()->connection,
            'failed_at'     => $this->getFailedAt(),
            'payload'       => $this->getResource()->payload,
            'exception'     => $this->getResource()->exception,
        ];
    }
}
