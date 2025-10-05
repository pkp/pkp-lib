<?php

/**
 * @file classes/migration/upgrade/v3_4_0/jobs/PreFixRegionCodesDaily.php
 *
 * Copyright (c) 2022-2025 Simon Fraser University
 * Copyright (c) 2022-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PreFixRegionCodesDaily
 *
 * @ingroup jobs
 *
 * @brief Marks the wrong region codes, that will need to be fixed, with a prefix.
 */

namespace PKP\migration\upgrade\v3_4_0\jobs;

use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\jobs\BaseJob;

class PreFixRegionCodesDaily extends BaseJob
{
    /** The last metrics_submission_geo_daily_id */
    protected int $lastId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $lastId)
    {
        parent::__construct();
        $this->lastId = $lastId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Mark the region codes that needs to be fixed by inserting the prefix 'pkp-' for them
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            DB::statement("
                UPDATE metrics_submission_geo_daily AS gd
                SET region = 'pkp-' || gd.region
                FROM region_mapping_tmp AS rm
                WHERE gd.country = rm.country AND gd.region = rm.fips AND gd.metrics_submission_geo_daily_id <= {$this->lastId}
            ");
        } else {
            DB::statement("
                UPDATE metrics_submission_geo_daily gd
                INNER JOIN region_mapping_tmp rm ON (rm.country = gd.country AND rm.fips = gd.region)
                SET gd.region = CONCAT('pkp-', gd.region)
                WHERE gd.metrics_submission_geo_daily_id <= {$this->lastId}
            ");
        }
    }
}
