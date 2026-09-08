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
 * @brief Job for marking a citation processed, once every lookup ahead of it in the chain has run.
 */

namespace PKP\jobs\citation;

use APP\facades\Repo;
use PKP\citation\enum\CitationProcessingStatus;
use PKP\jobs\BaseJob;

class IsProcessedJob extends BaseJob
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

        $citation->setProcessingStatus(CitationProcessingStatus::PROCESSED->value);

        Repo::citation()->edit($citation, []);
    }
}
