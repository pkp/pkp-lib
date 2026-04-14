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
 * @brief Loads all FIPS-ISO mappings for all countries into the temporary table.
 */

namespace PKP\migration\upgrade\v3_4_0\jobs;

use Exception;
use Illuminate\Support\Facades\DB;
use PKP\core\Core;
use PKP\jobs\BaseJob;

class RegionMappingTmpInsert extends BaseJob
{
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // clear any partial data from a previous failed run before re-inserting
        DB::table('region_mapping_tmp')->delete();

        // read all FIPS to ISO mappings
        $mappings = include Core::getBaseDir() . '/' . PKP_LIB_PATH . '/lib/regionMapping.php';

        // build batch insert array for all countries, only where FIPS differs from ISO
        $inserts = [];
        foreach ($mappings as $country => $regions) {
            foreach ($regions as $fips => $iso) {
                if ($fips !== $iso) {
                    $inserts[] = [
                        'country' => $country,
                        'fips' => $fips,
                        'iso' => $iso
                    ];
                }
            }
        }

        if (empty($inserts)) {
            throw new Exception('No FIPS-ISO mappings found in regionMapping.php — cannot proceed.');
        }
        DB::table('region_mapping_tmp')->insert($inserts);
    }
}
