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
use PKP\citation\pid\ExtractPidsHelper;
use PKP\job\exceptions\JobException;
use PKP\jobs\BaseJob;

class ExtractPidsJob extends BaseJob
{
    protected int $citationId;

    public function __construct(int $citationId)
    {
        parent::__construct();

        $this->citationId = $citationId;
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

        if ($citation->getIsProcessed()) {
            return;
        }

        $extractPids = new ExtractPidsHelper();

        Repo::citation()->edit($extractPids->execute($citation), []);
    }
}
