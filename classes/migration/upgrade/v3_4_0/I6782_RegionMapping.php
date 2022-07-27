<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I6782_RegionMapping.inc.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I6782_RegionMapping
 * @brief Loads the FIPS-ISO mapping to a temporary table.
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\core\Core;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I6782_RegionMapping extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        // migrate region FIPS to ISO, s. https://dev.maxmind.com/geoip/whats-new-in-geoip2?lang=en
        // create a temporary table for the FIPS-ISO mapping
        if (!Schema::hasTable('region_mapping_tmp')) {
            Schema::create('region_mapping_tmp', function (Blueprint $table) {
                $table->string('country', 2);
                $table->string('fips', 3);
                $table->string('iso', 3)->nullable();
            });
            // read the FIPS to ISO mappings and isert them into the temporary table
            $mappings = include Core::getBaseDir() . '/' . PKP_LIB_PATH . '/lib/regionMapping.php';
            foreach ($mappings as $country => $regionMapping) {
                foreach ($regionMapping as $fips => $iso) {
                    DB::table('region_mapping_tmp')->insert([
                        'country' => $country,
                        'fips' => $fips,
                        'iso' => $iso
                    ]);
                }
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
