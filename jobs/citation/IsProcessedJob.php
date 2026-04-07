<?php

/**
 * @file jobs/citation/IsProcessedJob.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class IsProcessedJob
 *
 * @ingroup jobs
 *
 * @brief Job for retrieving structured metadata for citations from external services.
 */

namespace PKP\jobs\citation;

use APP\facades\Repo;
use PKP\citation\enum\CitationProcessingStatus;
use PKP\job\exceptions\JobException;
use PKP\jobs\BaseJob;

class IsProcessedJob extends BaseJob implements \PKP\queue\ContextAwareJob
{
    protected int $citationId;
    protected int $contextId;

    public function __construct(int $citationId, int $contextId)
    {
        parent::__construct();
        $this->citationId = $citationId;
        $this->contextId = $contextId;
    }

    /**
     * Get the context ID for this job.
     */
    public function getContextId(): int
    {
        return $this->contextId;
    }

    /**
     * Handle the queue job execution process
     *
     * @throws JobException
     */
    public function handle(): void
    {
        $citation = Repo::citation()->get($this->citationId);

        if (!$citation) {
            throw new JobException(JobException::INVALID_PAYLOAD);
        }

        $citation->setProcessingStatus(CitationProcessingStatus::PROCESSED->value);

        Repo::citation()->edit($citation, []);
    }
}
