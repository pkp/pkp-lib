<?php

/**
 * @file classes/migration/upgrade/v3_4_0/jobs/FixRegionCodes.php
 *
 * Copyright (c) 2022-2026 Simon Fraser University
 * Copyright (c) 2022-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FixRegionCodes
 *
 * @ingroup jobs
 *
 * @brief Converts FIPS region codes to ISO codes in the geo metrics tables using the temporary
 * mapping table. Processes records in batches and re-queues itself until all records have been
 * converted, then runs cleanup inline.
 */

namespace PKP\migration\upgrade\v3_4_0\jobs;

use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\jobs\BaseJob;

class FixRegionCodes extends BaseJob
{
    public bool $failOnTimeout = false;
    public int $timeout = 300;

    private const BATCH_SIZE = 1000;

    private const TABLES = [
        'metrics_submission_geo_daily' => [
            'alias' => 'gd',
            'idColumn' => 'metrics_submission_geo_daily_id',
            'tmpIndex' => 'metrics_submission_geo_daily_tmp_index',
        ],
        'metrics_submission_geo_monthly' => [
            'alias' => 'gm',
            'idColumn' => 'metrics_submission_geo_monthly_id',
            'tmpIndex' => 'metrics_submission_geo_monthly_tmp_index',
        ],
    ];

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $processedAny = false;

        foreach (self::TABLES as $table => $config) {
            $alias = $config['alias'];
            $idColumn = $config['idColumn'];

            // fetch a batch of IDs that still need processing (needs_review IS NULL);
            // IDs are fetched separately to allow batching — PostgreSQL does not support
            // UPDATE ... LIMIT, so we cannot apply the limit directly in the update query
            $ids = DB::table($table)
                ->whereNull('needs_review')
                ->limit(self::BATCH_SIZE)
                ->pluck($idColumn);

            if ($ids->isEmpty()) {
                continue;
            }

            // convert FIPS to ISO for records that have a mapping in the temporary table and
            // atomically mark them as done; uses an INNER JOIN so only rows with a matching
            // FIPS code are written to — setting needs_review in the same statement ensures
            // that if the job is re-run, converted rows are not picked up again (which would
            // cause a chained re-conversion to a wrong code)
            $query = DB::table($table . ' as ' . $alias)
                ->join('region_mapping_tmp as rm', function ($join) use ($alias) {
                    $join->on("{$alias}.country", '=', 'rm.country')
                        ->on("{$alias}.region", '=', 'rm.fips');
                })
                ->whereIn("{$alias}.{$idColumn}", $ids);

            if (DB::connection() instanceof PostgresConnection) {
                $query->updateFrom(["{$alias}.region" => DB::raw('rm.iso'), 'needs_review' => 0]);
            } else {
                $query->update(["{$alias}.region" => DB::raw('rm.iso'), 'needs_review' => 0]);
            }

            // mark remaining batch records (those without a FIPS mapping) as done;
            // a no-op for rows already marked above by the INNER JOIN update
            DB::table($table)->whereIn($idColumn, $ids)->update(['needs_review' => 0]);

            $processedAny = true;
        }

        if ($processedAny) {
            // more records remain — dispatch a fresh job instance to continue in the next batch;
            // avoids accumulating exceptions from transient errors (e.g. database locks) across batches
            dispatch(new static());
            return;
        }

        // no records left to process — run cleanup inline
        $this->cleanup();
    }

    /**
     * Drop temporary indexes, the needs_review column, and the region_mapping_tmp table.
     */
    private function cleanup(): void
    {
        foreach (self::TABLES as $table => $config) {
            Schema::table($table, function (Blueprint $t) use ($table, $config) {
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                $indexesFound = $sm->listTableIndexes($table);
                if (array_key_exists($config['tmpIndex'], $indexesFound)) {
                    $t->dropIndex(['tmp']);
                }
            });

            if (Schema::hasColumn($table, 'needs_review')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('needs_review');
                });
            }
        }

        if (Schema::hasTable('region_mapping_tmp')) {
            Schema::drop('region_mapping_tmp');
        }
    }
}
