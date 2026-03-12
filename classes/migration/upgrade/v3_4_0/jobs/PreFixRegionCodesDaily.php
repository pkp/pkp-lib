<?php

/**
 * @file classes/migration/upgrade/v3_4_0/jobs/PreFixRegionCodesDaily.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PreFixRegionCodesDaily
 *
 * @ingroup jobs
 *
 * @brief Marks the wrong region codes, that will need to be fixed, with a prefix.
 */

namespace PKP\migration\upgrade\v3_4_0\jobs;

use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Facades\DB;
use PKP\jobs\BaseJob;

class PreFixRegionCodesDaily extends BaseJob
{
    /**
     * Create a new job instance, using the range of metrics_submission_geo_daily_ids to consider for this update
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
        $query = DB::table('metrics_submission_geo_daily as gd')
            ->join('region_mapping_tmp as rm', function ($join) {
                $join->on('gd.country', '=', 'rm.country')
                    ->on('gd.region', '=', 'rm.fips');
            })
            ->whereBetween('gd.metrics_submission_geo_daily_id', [$this->startId, $this->endId]);

        if (DB::connection() instanceof PostgresConnection) {
            $query->updateFrom(['gd.region' => DB::raw("CONCAT('pkp-', gd.region)")]);
        } else {
            $query->update(['gd.region' => DB::raw("CONCAT('pkp-', gd.region)")]);
        }
    }
}
