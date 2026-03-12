<?php

/**
 * @file classes/migration/upgrade/v3_4_0/jobs/PreFixRegionCodesMonthly.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
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

use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Facades\DB;
use PKP\jobs\BaseJob;

class PreFixRegionCodesMonthly extends BaseJob
{
    /**
     * Create a new job instance, using the range of metrics_submission_geo_monthly_ids to consider for this update
     */
    public function __construct(private int $startId, private int $endId)
    {
        parent::__construct();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Mark the region codes that need to be fixed by inserting the prefix 'pkp-' for them
        $query = DB::table('metrics_submission_geo_monthly as gm')
            ->join('region_mapping_tmp as rm', function ($join) {
                $join->on('gm.country', '=', 'rm.country')
                    ->on('gm.region', '=', 'rm.fips');
            })
            ->whereBetween('gm.metrics_submission_geo_monthly_id', [$this->startId, $this->endId]);

        if (DB::connection() instanceof PostgresConnection) {
            $query->updateFrom(['gm.region' => DB::raw("CONCAT('pkp-', gm.region)")]);
        } else {
            $query->update(['gm.region' => DB::raw("CONCAT('pkp-', gm.region)")]);
        }
    }
}
