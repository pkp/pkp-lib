<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I8866_DispatchRegionCodesFixingJobs.php
 *
 * Copyright (c) 2022-2026 Simon Fraser University
 * Copyright (c) 2022-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I8866_DispatchRegionCodesFixingJobs
 *
 * @brief Dispatches background jobs to convert FIPS region codes to ISO codes in the geo metrics tables, if any non-empty region codes exist.
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;
use PKP\migration\upgrade\v3_4_0\jobs\FixRegionCodes;
use PKP\migration\upgrade\v3_4_0\jobs\RegionMappingTmpInsert;

class I8866_DispatchRegionCodesFixingJobs extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        if (!DB::table('metrics_submission_geo_monthly')->where('region', '<>', '')->exists() &&
            !DB::table('metrics_submission_geo_daily')->where('region', '<>', '')->exists()) {

            return;
        }

        // create a temporary table for the FIPS-ISO mapping
        if (!Schema::hasTable('region_mapping_tmp')) {
            Schema::create('region_mapping_tmp', function (Blueprint $table) {
                $table->string('country', 2);
                $table->string('fips', 3);
                $table->string('iso', 3);
                $table->index(['country', 'fips']);
            });
        }

        // add needs_review column: NULL marks existing records that need processing,
        // default 0 ensures new records inserted during processing are excluded
        foreach (['metrics_submission_geo_daily', 'metrics_submission_geo_monthly'] as $table) {
            if (!Schema::hasColumn($table, 'needs_review')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->smallInteger('needs_review')->nullable();
                });
                // Set the default separately rather than adding the column with a default value,
                // to avoid a table rewrite in some databases (PostgreSQL < 11, MySQL 5.7).
                // Laravel migration for updating column attributes results in
                // ALTER TABLE ... CHANGE in some databases (MySQL 5.7), thus use a raw statement.
                DB::statement("ALTER TABLE {$table} ALTER COLUMN needs_review SET DEFAULT 0");
            }
        }

        // temporarily add an index on (country, region) to speed up the region code updates
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

        Bus::chain([
            new RegionMappingTmpInsert(),
            new FixRegionCodes(),
        ])->dispatch();
    }

    /**
     * Reverse the migration
     *
     * @throws DowngradeNotSupportedException
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
