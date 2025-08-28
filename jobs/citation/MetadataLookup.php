<?php

/**
 * @file jobs/citation/MetadataLookup.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MetadataLookup
 *
 * @ingroup jobs
 *
 * @brief Job for retrieving structured metadata for citations from external services.
 */

namespace PKP\jobs\citation;

use PKP\citation\job\MetadataLookupHandler;
use PKP\job\exceptions\JobException;
use PKP\jobs\BaseJob;

class MetadataLookup extends BaseJob
{
    protected int $citationId;

    public function __construct(int $citationId)
    {
        parent::__construct();

        $this->citationId = $citationId;
    }

    public function handle(): void
    {
        if (!$this->citationId) {
            throw new JobException(JobException::INVALID_PAYLOAD);
        }

        new MetadataLookupHandler($this->citationId);
    }
}
