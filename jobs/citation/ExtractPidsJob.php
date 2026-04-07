<?php

/**
 * @file jobs/citation/ExtractPidsJob.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ExtractPidsJob
 *
 * @ingroup jobs
 *
 * @brief Job for retrieving structured metadata for citations from external services.
 */

namespace PKP\jobs\citation;

use APP\facades\Repo;
use PKP\citation\enum\CitationProcessingStatus;
use PKP\citation\pid\ExtractPidsHelper;
use PKP\job\exceptions\JobException;
use PKP\jobs\BaseJob;

class ExtractPidsJob extends BaseJob implements \PKP\queue\ContextAwareJob
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

        if ($citation->getProcessingStatus() >= CitationProcessingStatus::PID_EXTRACTED->value) {
            return;
        }

        $extractPids = new ExtractPidsHelper();
        $citationChanged = $extractPids->execute($citation);
        $citationChanged->setProcessingStatus(CitationProcessingStatus::PID_EXTRACTED->value);
        Repo::citation()->edit($citationChanged, []);
    }
}
