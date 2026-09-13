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
 * @brief Job for extracting the identifiers a citation's raw text already carries.
 */

namespace PKP\jobs\citation;

use APP\facades\Repo;
use PKP\citation\enum\CitationProcessingStatus;
use PKP\citation\pid\ExtractPidsHelper;
use PKP\jobs\BaseJob;

class ExtractPidsJob extends BaseJob
{
    protected int $contextId;
    protected int $citationId;

    public function __construct(int $contextId, int $citationId)
    {
        parent::__construct();
        $this->contextId = $contextId;
        $this->citationId = $citationId;
    }

    /**
     * Handle the queue job execution process
     */
    public function handle(): void
    {
        $citation = Repo::citation()->get($this->citationId);

        if (!$citation) {
            return;
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
