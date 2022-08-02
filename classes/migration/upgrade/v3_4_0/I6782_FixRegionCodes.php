<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I6782_FixRegionCodes.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I6782_FixRegionCodes
 * @brief Fixes the wrong region codes, using the temporary table of FIPS-ISO mapping ceated in I6782_RegionMapping, then remove the temporary table.
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\config\Config;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I6782_FixRegionCodes extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        // temporary create index on the column country and region, in order to be able to update the region codes in a reasonable time
        Schema::table('metrics_submission_geo_daily', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexesFound = $sm->listTableIndexes('metrics_submission_geo_daily');
            if (!array_key_exists('metrics_submission_geo_daily_tmp_index', $indexesFound)) {
                $table->index(['country', 'region'], 'metrics_submission_geo_daily_tmp_index');
            }
        });
        Schema::table('metrics_submission_geo_monthly', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexesFound = $sm->listTableIndexes('metrics_submission_geo_monthly');
            if (!array_key_exists('metrics_submission_geo_monthly_tmp_index', $indexesFound)) {
                $table->index(['country', 'region'], 'metrics_submission_geo_monthly_tmp_index');
            }
        });

        // update region code from FIPS to ISP
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

        // drop the temporary index
        Schema::table('metrics_submission_geo_daily', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexesFound = $sm->listTableIndexes('metrics_submission_geo_daily');
            if (array_key_exists('metrics_submission_geo_daily_tmp_index', $indexesFound)) {
                $table->dropIndex(['tmp']);
            }
        });
        Schema::table('metrics_submission_geo_monthly', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexesFound = $sm->listTableIndexes('metrics_submission_geo_monthly');
            if (array_key_exists('metrics_submission_geo_monthly_tmp_index', $indexesFound)) {
                $table->dropIndex(['tmp']);
            }
        });

        // drop the temporary table
        if (Schema::hasTable('region_mapping_tmp')) {
            Schema::drop('region_mapping_tmp');
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
