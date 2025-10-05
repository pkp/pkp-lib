<?php

/**
 * @file classes/migration/upgrade/v3_4_0/jobs/RegionMappingTmpClear.php
 *
 * Copyright (c) 2022-2025 Simon Fraser University
 * Copyright (c) 2022-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RegionMappingTmpClear
 *
 * @ingroup jobs
 *
 * @brief Clears the FIPS-ISO mapping temporary table
 */

namespace PKP\migration\upgrade\v3_4_0\jobs;

use Illuminate\Support\Facades\DB;
use PKP\jobs\BaseJob;

class RegionMappingTmpClear extends BaseJob
{
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // clear region_mapping_tmp table
        DB::table('region_mapping_tmp')->delete();
    }
}
