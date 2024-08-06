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

use Illuminate\Bus\Batch;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            // create a temporary table for the FIPS-ISO mapping
            if (!Schema::hasTable('region_mapping_tmp')) {
                Schema::create('region_mapping_tmp', function (Blueprint $table) {
                    $table->string('country', 2);
                    $table->string('fips', 3);
                    $table->string('iso', 3)->nullable();
                });
            }

            // temporary create index on the column country and region, in order to be able to update the region codes in a reasonable time
            Schema::table('metrics_submission_geo_daily', function (Blueprint $table) {
                if (!Schema::hasIndex('metrics_submission_geo_daily', 'metrics_submission_geo_daily_tmp_index')) {
                    $table->index(['country', 'region'], 'metrics_submission_geo_daily_tmp_index');
                }
            });
            Schema::table('metrics_submission_geo_monthly', function (Blueprint $table) {
                if (!Schema::hasIndex('metrics_submission_geo_monthly', 'metrics_submission_geo_monthly_tmp_index')) {
                    $table->index(['country', 'region'], 'metrics_submission_geo_monthly_tmp_index');
                }
            });

            // read the FIPS to ISO mappings and displatch a job per country
            $mappings = include Core::getBaseDir() . '/' . PKP_LIB_PATH . '/lib/regionMapping.php';
            $jobs = [];
            foreach (array_keys($mappings) as $country) {
                $jobs[] = new FixRegionCodes($country);
            }

            Bus::batch($jobs)
                ->then(function (Batch $batch) {
                    // drop the temporary index
                    Schema::table('metrics_submission_geo_daily', function (Blueprint $table) {
                        if (Schema::hasIndex('metrics_submission_geo_daily', 'metrics_submission_geo_daily_tmp_index')) {
                            $table->dropIndex(['tmp']);
                        }
                    });
                    Schema::table('metrics_submission_geo_monthly', function (Blueprint $table) {
                        if (Schema::hasIndex('metrics_submission_geo_monthly', 'metrics_submission_geo_monthly_tmp_index')) {
                            $table->dropIndex(['tmp']);
                        }
                    });

                    // drop the temporary table
                    if (Schema::hasTable('region_mapping_tmp')) {
                        Schema::drop('region_mapping_tmp');
                    }
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
