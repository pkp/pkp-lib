<?php

/**
 * @file classes/migration/upgrade/v3_4_0/jobs/PreFixRegionCodesMonthly.php
 *
 * Copyright (c) 2022-2025 Simon Fraser University
 * Copyright (c) 2022-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PreFixRegionCodesMonthly
 *
 * @ingroup jobs
 *
 * @brief Loads the FIPS-ISO mapping for the given country to the temporary table and fixes the wrong region codes, using the temporary table of FIPS-ISO mapping.
 * If it is the last job to run, it removes the temporary indexes and the temporary table.
 */

namespace PKP\migration\upgrade\v3_4_0\jobs;

use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\jobs\BaseJob;

class PreFixRegionCodesMonthly extends BaseJob
{
    /** The range of metrics_submission_geo_monthly_ids to consider for this update */
    protected int $startId;
    protected int $endId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $startId, int $endId)
    {
        parent::__construct();
        $this->startId = $startId;
        $this->endId = $endId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Mark the region codes that needs to be fixed by inserting the prefix 'pkp-' for them
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            DB::statement("
                UPDATE metrics_submission_geo_monthly AS gm
                SET region = 'pkp-' || gm.region
                FROM region_mapping_tmp AS rm
                WHERE gm.country = rm.country AND
					gm.region = rm.fips AND
					gm.metrics_submission_geo_monthly_id >= {$this->startId} AND
					gm.metrics_submission_geo_monthly_id <= {$this->endId}
            ");
        } else {
            DB::statement("
                UPDATE metrics_submission_geo_monthly gm
                INNER JOIN region_mapping_tmp rm ON (rm.country = gm.country AND rm.fips = gm.region)
                SET gm.region = CONCAT('pkp-', gm.region)
				WHERE gm.metrics_submission_geo_monthly_id >= {$this->startId} AND
					gm.metrics_submission_geo_monthly_id <= {$this->endId}
            ");
        }
    }
}
