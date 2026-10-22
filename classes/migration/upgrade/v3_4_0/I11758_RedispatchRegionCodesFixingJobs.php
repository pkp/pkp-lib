<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I11758_RedispatchRegionCodesFixingJobs.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I11758_RedispatchRegionCodesFixingJobs
 *
 * @brief Dispatches the region codes fixing jobs again, if a previous run failed and left its temporary data behind.
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class I11758_RedispatchRegionCodesFixingJobs extends I8866_DispatchRegionCodesFixingJobs
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        // region_mapping_tmp is only dropped when all rows have been converted, so it still exists if a
        // previous run did not finish. Without needs_review, it was left behind by the older version of
        // the job in 3.4.0-10 and earlier, which did not record which rows have been converted. Running
        // the conversion there could convert some rows twice, so such data is left as it is.
        if (!Schema::hasTable('region_mapping_tmp') ||
            !Schema::hasColumn('metrics_submission_geo_daily', 'needs_review') ||
            !Schema::hasColumn('metrics_submission_geo_monthly', 'needs_review')) {
            return;
        }

        // a FixRegionCodes job is still waiting to run, so the conversion will be completed anyway
        if (DB::table('jobs')->where('payload', 'like', '%FixRegionCodes%')->exists()) {
            return;
        }

        // needs_review already exists and is kept, so the rows converted by the previous run
        // still have needs_review = 0 and are not converted again
        parent::up();
    }
}
