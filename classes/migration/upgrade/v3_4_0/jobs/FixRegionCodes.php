<?php

/**
 * @file classes/migration/upgrade/v3_4_0/jobs/FixRegionCodes.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FixRegionCodes
 *
 * @ingroup jobs
 *
 * @brief Loads the FIPS-ISO mapping for the given country to the temporary table and fixes the wrong region codes, using the temporary table of FIPS-ISO mapping.
 * If it is the last job to run, it removes the temporary indexes and the temporary table.
 */

namespace PKP\migration\upgrade\v3_4_0\jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\core\Core;
use PKP\jobs\BaseJob;

class FixRegionCodes extends BaseJob
{
    use Batchable;

    /** The country ISO code */
    protected string $country;

    /**
     * Create a new job instance.
     */
    public function __construct(string $country)
    {
        parent::__construct();
        $this->country = $country;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // read the FIPS to ISO mappings for the given country and isert them into the temporary table
        $mappings = include Core::getBaseDir() . '/' . PKP_LIB_PATH . '/lib/regionMapping.php';
        foreach ($mappings[$this->country] as $fips => $iso) {
            DB::table('region_mapping_tmp')->insert([
                'country' => $this->country,
                'fips' => $fips,
                'iso' => $iso
            ]);
        }

        // update region code from FIPS to ISP, according to the entries in the table region_mapping_tmp
        // Laravel join+update does not work well with PostgreSQL, so use the direct SQLs
        // daily
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            DB::statement('
                UPDATE metrics_submission_geo_daily AS gd
                SET region = rm.iso
                FROM region_mapping_tmp AS rm
                WHERE gd.country = rm.country AND gd.region = rm.fips
            ');
        } else {
            DB::statement('
                UPDATE metrics_submission_geo_daily gd
                INNER JOIN region_mapping_tmp rm ON (rm.country = gd.country AND rm.fips = gd.region)
                SET gd.region = rm.iso
            ');
        }
        // montly
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            DB::statement('
                UPDATE metrics_submission_geo_monthly AS gm
                SET region = rm.iso
                FROM region_mapping_tmp AS rm
                WHERE gm.country = rm.country AND gm.region = rm.fips
            ');
        } else {
            DB::statement('
                UPDATE metrics_submission_geo_monthly gm
                INNER JOIN region_mapping_tmp rm ON (rm.country = gm.country AND rm.fips = gm.region)
                SET gm.region = rm.iso
            ');
        }

        // clear region_mapping_tmp table
        DB::table('region_mapping_tmp')->delete();
    }
}
