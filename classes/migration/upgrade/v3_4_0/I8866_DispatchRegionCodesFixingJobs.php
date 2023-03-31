<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I8866_DispatchRegionCodesFixingJobs.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I8866_DispatchRegionCodesFixingJobs
 * @brief Dispatches the jobs (a job per coutry) that shell fix the old region codes, if needed i.e. if any region exists.
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Support\Facades\DB;
use PKP\core\Core;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;
use PKP\migration\upgrade\v3_4_0\jobs\FixRegionCodes;

class I8866_DispatchRegionCodesFixingJobs extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        if (DB::table('metrics_submission_geo_monthly')->whereNotNull('region')->exists() ||
            DB::table('metrics_submission_geo_daily')->whereNotNull('region')->exists()) {

            // read the FIPS to ISO mappings and displatch a job per country
            $mappings = include Core::getBaseDir() . '/' . PKP_LIB_PATH . '/lib/regionMapping.php';
            $lastCountry = array_key_last($mappings);
            foreach (array_keys($mappings) as $country) {
                $lastJob = $country == $lastCountry ? true : false;
                dispatch(new FixRegionCodes($country, $lastJob));
            }
        }
    }

    /**
     * Reverse the downgrades
     *
     * @throws DowngradeNotSupportedException
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
