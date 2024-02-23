<?php

/**
 * @file jobs/statistics/RemoveDoubleClicks.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RemoveDoubleClicks
 *
 * @ingroup jobs
 *
 * @brief Remove Double Clicks according to COUNTER guidelines.
 */

namespace PKP\jobs\statistics;

use APP\statistics\StatisticsHelper;
use APP\statistics\TemporaryTotalsDAO;
use PKP\db\DAORegistry;
use PKP\jobs\BaseJob;

class RemoveDoubleClicks extends BaseJob
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
        $temporaryTotalsDao->removeDoubleClicks($this->loadId, StatisticsHelper::COUNTER_DOUBLE_CLICK_TIME_FILTER_SECONDS);
    }
}
