<?php

/**
 * @file classes/migration/upgrade/v3_4_0/jobs/FixRegionCodesDaily.php
 *
 * Copyright (c) 2022-2025 Simon Fraser University
 * Copyright (c) 2022-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FixRegionCodesDaily
 *
 * @ingroup jobs
 *
 * @brief Fixes the wrong region codes, using the temporary table of FIPS-ISO mapping.
 */

namespace PKP\migration\upgrade\v3_4_0\jobs;

use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\jobs\BaseJob;

class FixRegionCodesDaily extends BaseJob
{
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // update region code from FIPS to ISP, according to the entries in the table region_mapping_tmp
        // Laravel join+update does not work well with PostgreSQL, so use the direct SQLs
        // daily
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            DB::statement('
                UPDATE metrics_submission_geo_daily AS gd
                SET region = rm.iso
                FROM region_mapping_tmp AS rm
                WHERE gd.country = rm.country AND gd.region = "pkp-" || rm.fips
            ');
        } else {
            DB::statement('
                UPDATE metrics_submission_geo_daily gd
                INNER JOIN region_mapping_tmp rm ON (rm.country = gd.country AND CONCAT("pkp-", rm.fips) = gd.region)
                SET gd.region = rm.iso
            ');
        }
    }
}
