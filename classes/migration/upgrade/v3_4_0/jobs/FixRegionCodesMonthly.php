<?php

/**
 * @file classes/migration/upgrade/v3_4_0/jobs/FixRegionCodesMonthly.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FixRegionCodesMonthly
 *
 * @ingroup jobs
 *
 * @brief Fixes the wrong region codes, using the temporary table of FIPS-ISO mapping.
 */

namespace PKP\migration\upgrade\v3_4_0\jobs;

use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Facades\DB;
use PKP\jobs\BaseJob;

class FixRegionCodesMonthly extends BaseJob
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
        // update region code from FIPS to ISO, according to the entries in the table region_mapping_tmp
        $query = DB::table('metrics_submission_geo_monthly as gm')
            ->join('region_mapping_tmp as rm', function ($join) {
                $join->on('gm.country', '=', 'rm.country')
                    ->on('gm.region', '=', DB::raw("CONCAT('pkp-', rm.fips)"));
            })
            ->whereBetween('gm.metrics_submission_geo_monthly_id', [$this->startId, $this->endId]);

        if (DB::connection() instanceof PostgresConnection) {
            $query->updateFrom(['gm.region' => DB::raw('rm.iso')]);
        } else {
            $query->update(['gm.region' => DB::raw('rm.iso')]);
        }
    }
}
