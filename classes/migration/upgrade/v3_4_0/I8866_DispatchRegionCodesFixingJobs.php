<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I8866_DispatchRegionCodesFixingJobs.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I8866_DispatchRegionCodesFixingJobs
 *
 * @brief Dispatches the jobs (a job per country) that shell fix the old region codes, if needed i.e. if any region exists.
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\core\Core;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;
use PKP\migration\upgrade\v3_4_0\jobs\CleanTmpChangesForRegionCodesFixes;
use PKP\migration\upgrade\v3_4_0\jobs\FixRegionCodesDaily;
use PKP\migration\upgrade\v3_4_0\jobs\FixRegionCodesMonthly;
use PKP\migration\upgrade\v3_4_0\jobs\PreFixRegionCodesDaily;
use PKP\migration\upgrade\v3_4_0\jobs\PreFixRegionCodesMonthly;
use PKP\migration\upgrade\v3_4_0\jobs\RegionMappingTmpClear;
use PKP\migration\upgrade\v3_4_0\jobs\RegionMappingTmpInsert;
use Throwable;

class I8866_DispatchRegionCodesFixingJobs extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        if (DB::table('metrics_submission_geo_monthly')->where('region', '<>', '')->exists() ||
            DB::table('metrics_submission_geo_daily')->where('region', '<>', '')->exists()) {

            // create a temporary table for the FIPS-ISO mapping
            if (!Schema::hasTable('region_mapping_tmp')) {
                Schema::create('region_mapping_tmp', function (Blueprint $table) {
                    $table->string('country', 2);
                    $table->string('fips', 3);
                    $table->string('iso', 3)->nullable();
                });
            }

            // temporary change the length of the region columns, becuase we will add prefix 'pkp-'
            Schema::table('metrics_submission_geo_daily', function (Blueprint $table) {
                $table->string('region', 7)->change();
            });
            Schema::table('metrics_submission_geo_monthly', function (Blueprint $table) {
                $table->string('region', 7)->change();
            });

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

            $lastGeoDailyId = DB::table('metrics_submission_geo_daily')
                ->max('metrics_submission_geo_daily_id');
            $lastGeoMonthlyId = DB::table('metrics_submission_geo_monthly')
                ->max('metrics_submission_geo_monthly_id');

            // read the FIPS to ISO mappings and displatch a job per country
            $mappings = include Core::getBaseDir() . '/' . PKP_LIB_PATH . '/lib/regionMapping.php';
            $jobs = [];
            foreach (array_keys($mappings) as $country) {
                $jobsPerCountry = [
                    new RegionMappingTmpInsert($country),
                    new PreFixRegionCodesDaily($lastGeoDailyId),
                    new PreFixRegionCodesMonthly($lastGeoMonthlyId),
                    new FixRegionCodesDaily(),
                    new FixRegionCodesMonthly(),
                    new RegionMappingTmpClear(),
                ];
                $jobs = array_merge($jobs, $jobsPerCountry);
            }
            $jobs[] = new CleanTmpChangesForRegionCodesFixes();
            Bus::chain($jobs)
                ->catch(function (Throwable $e) {
                })
                ->dispatch();
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
