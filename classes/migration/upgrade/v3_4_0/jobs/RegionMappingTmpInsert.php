<?php

/**
 * @file classes/migration/upgrade/v3_4_0/jobs/RegionMappingTmpInsert.php
 *
 * Copyright (c) 2022-2025 Simon Fraser University
 * Copyright (c) 2022-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RegionMappingTmpInsert
 *
 * @ingroup jobs
 *
 * @brief Loads the FIPS-ISO mapping for the given country to the temporary table.
 */

namespace PKP\migration\upgrade\v3_4_0\jobs;

use Illuminate\Support\Facades\DB;
use PKP\core\Core;
use PKP\jobs\BaseJob;

class RegionMappingTmpInsert extends BaseJob
{
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
        // clear region_mapping_tmp table
        DB::table('region_mapping_tmp')->delete();

        // read the FIPS to ISO mappings for the given country and insert them into the temporary table
        $mappings = include Core::getBaseDir() . '/' . PKP_LIB_PATH . '/lib/regionMapping.php';
        foreach ($mappings[$this->country] as $fips => $iso) {
            DB::table('region_mapping_tmp')->insert([
                'country' => $this->country,
                'fips' => $fips,
                'iso' => $iso
            ]);
        }
    }
}
