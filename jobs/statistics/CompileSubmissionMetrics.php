<?php

/**
 * @file jobs/statistics/CompileSubmissionMetrics.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CompileSubmissionMetrics
 *
 * @ingroup jobs
 *
 * @brief Compile submission metrics.
 */

namespace PKP\jobs\statistics;

use APP\statistics\TemporaryTotalsDAO;
use PKP\db\DAORegistry;
use PKP\jobs\BaseJob;

class CompileSubmissionMetrics extends BaseJob
{
    /**
     * The load ID = usage stats log file name
     */
    protected string $loadId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $loadId)
    {
        parent::__construct();
        $this->loadId = $loadId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $temporaryTotalsDao = DAORegistry::getDAO('TemporaryTotalsDAO'); /** @var TemporaryTotalsDAO $temporaryTotalsDao */
        $temporaryTotalsDao->compileSubmissionMetrics($this->loadId);
    }
}
