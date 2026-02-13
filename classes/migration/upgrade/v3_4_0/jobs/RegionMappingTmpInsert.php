<?php

/**
 * @file classes/migration/upgrade/v3_4_0/jobs/RegionMappingTmpInsert.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
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
    /**
     * Create a new job instance.
     */
    public function __construct(private string $country)
    {
        parent::__construct();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // clear region_mapping_tmp table
        DB::table('region_mapping_tmp')->delete();

        // read the FIPS to ISO mappings for the given country
        $mappings = include Core::getBaseDir() . '/' . PKP_LIB_PATH . '/lib/regionMapping.php';

        // build batch insert array for mappings where FIPS differs from ISO
        $inserts = [];
        foreach ($mappings[$this->country] as $fips => $iso) {
            if ($fips !== $iso) {
                $inserts[] = [
                    'country' => $this->country,
                    'fips' => $fips,
                    'iso' => $iso
                ];
            }
        }

        // insert all mappings in one batch
        if (!empty($inserts)) {
            DB::table('region_mapping_tmp')->insert($inserts);
        }
    }
}
