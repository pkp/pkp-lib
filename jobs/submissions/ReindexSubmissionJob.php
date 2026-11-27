<?php

declare(strict_types=1);

/**
 * @file jobs/submissions/ReindexSubmissionJob.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReindexSubmissionJob
 *
 * @ingroup jobs
 *
 * @brief Re-index a submission in the configured search engine, e.g. after its public
 *   peer review progressed. Runs as a job because indexing may parse galley files.
 */

namespace PKP\jobs\submissions;

use APP\facades\Repo;
use Laravel\Scout\EngineManager;
use PKP\jobs\BaseJob;

class ReindexSubmissionJob extends BaseJob
{
    public int $timeout = 180;

    public function __construct(protected int $submissionId)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $submission = Repo::submission()->get($this->submissionId);
        if (!$submission) {
            return;
        }

        app(EngineManager::class)->engine()->update(collect([$submission]));
    }
}
