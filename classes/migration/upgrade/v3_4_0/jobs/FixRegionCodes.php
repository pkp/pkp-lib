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
 * @brief Converts the FIPS region codes in the geo metrics tables to ISO codes, using the FIPS-ISO
 * mapping in the temporary table region_mapping_tmp.
 *
 * Rows still to be converted have needs_review = NULL. Converted rows, and rows inserted after the
 * upgrade, have needs_review = 0, so that no row is converted twice. Each job converts about
 * BATCH_SIZE rows per table and then dispatches a new job that continues where it stopped.
 * The last job removes all temporary tables, columns and indexes.
 */

namespace PKP\migration\upgrade\v3_4_0\jobs;

use Exception;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\jobs\BaseJob;

class FixRegionCodes extends BaseJob
{
    public bool $failOnTimeout = false;
    public int $timeout = 300;

    // Approximate number of rows per table converted by one job.
    // A submission with more rows to convert is converted country by country, to keep the transactions small.
    private const BATCH_SIZE = 10000;

    private const TABLES = [
        'metrics_submission_geo_daily' => [
            'alias' => 'gd',
            'idColumn' => 'metrics_submission_geo_daily_id',
            'tmpIndexes' => [
                // (country, region), created by an earlier version of this job (3.4.0-11, 3.5.0-4, 3.5.0-5)
                'metrics_submission_geo_daily_tmp_index',
                // (submission_id, country), because the unique key starts with load_id and cannot be used for that
                'msgd_tmp_submission_country',
            ],
            'uniqueKey' => 'msgd_uc_load_context_submission_c_r_c_date',
            'keyColumns' => ['load_id', 'context_id', 'submission_id', 'country', 'region', 'city', 'date'],
        ],
        'metrics_submission_geo_monthly' => [
            'alias' => 'gm',
            'idColumn' => 'metrics_submission_geo_monthly_id',
            'tmpIndexes' => [
                // (country, region), created by an earlier version of this job (3.4.0-11, 3.5.0-4, 3.5.0-5);
                // no other index is needed, because the unique key starts with context_id, submission_id, country
                'metrics_submission_geo_monthly_tmp_index',
            ],
            'uniqueKey' => 'msgm_uc_context_submission_c_r_c_month',
            'keyColumns' => ['context_id', 'submission_id', 'country', 'region', 'city', 'month'],
        ],
    ];

    /**
     * @param array $lastPositions Where the previous job stopped, per table: [submission ID, country].
     *  The country is null if the whole submission has been converted, otherwise the submission is
     *  continued with the next country.
     */
    public function __construct(protected array $lastPositions = [])
    {
        parent::__construct();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // the conversion has already been completed and its temporary data removed, e.g. by another job
        if (!Schema::hasTable('region_mapping_tmp')) {
            return;
        }
        // without the mapping all rows would be marked as converted without having been converted
        if (!DB::table('region_mapping_tmp')->exists()) {
            throw new Exception('The temporary FIPS-ISO mapping table region_mapping_tmp is empty.');
        }
        if (!Schema::hasTable('region_fix_tmp')) {
            $this->createWorkTable();
        }

        $processedAny = false;

        foreach (self::TABLES as $table => $config) {
            // The unique key contains submission_id and country, so a row can only collide with rows of the
            // same submission and country. A submission is therefore converted as a whole, and a large one
            // country by country, each in its own transaction.
            $budget = self::BATCH_SIZE;
            [$submissionId, $lastCountry] = $this->lastPositions[$table] ?? [0, null];
            while ($budget > 0) {
                if ($lastCountry === null) {
                    $submissionId = DB::table($table)->where('submission_id', '>', $submissionId)->min('submission_id');
                    if ($submissionId === null) {
                        break;
                    }
                    $submissionId = (int) $submissionId;
                }
                // context_id is added to the queries, so that the database can use the indexes starting with context_id, submission_id
                $contextId = (int) DB::table($table)->where('submission_id', $submissionId)->value('context_id');

                if ($lastCountry === null) {
                    $rowsToConvert = DB::table($table)
                        ->where('context_id', $contextId)
                        ->where('submission_id', $submissionId)
                        ->whereNull('needs_review')
                        ->count();
                    if ($rowsToConvert <= self::BATCH_SIZE) {
                        if ($rowsToConvert > 0) {
                            DB::transaction(fn () => $this->fixRegionCodes($table, $config, $contextId, $submissionId));
                        }
                        // every submission counts at least 1, so that a job skipping many already converted submissions also ends
                        $budget -= max(1, $rowsToConvert);
                        $this->lastPositions[$table] = [$submissionId, null];
                        $processedAny = true;
                        continue;
                    }
                }

                $countries = DB::table($table)
                    ->where('context_id', $contextId)
                    ->where('submission_id', $submissionId)
                    ->when($lastCountry !== null, fn (Builder $query) => $query->where('country', '>', $lastCountry))
                    ->distinct()
                    ->orderBy('country')
                    ->pluck('country');
                foreach ($countries as $country) {
                    $processedRows = DB::transaction(fn () => $this->fixRegionCodes($table, $config, $contextId, $submissionId, $country));

                    $budget -= max(1, $processedRows);
                    $this->lastPositions[$table] = [$submissionId, $country];
                    $processedAny = true;
                    if ($budget <= 0) {
                        break 2;
                    }
                }
                $lastCountry = null;
            }
        }

        if ($processedAny) {
            // More rows may remain, so a new job continues where this one stopped. A new job instead of
            // releasing this one back to the queue, so that exceptions (e.g. from database locks) do not
            // add up across all batches.
            dispatch(new static($this->lastPositions));
            return;
        }

        // all rows have been converted
        $this->cleanup();
    }

    /**
     * Convert all rows of a submission, or of a submission's country, that still need to be converted.
     *
     * Updating the region of the rows one by one fails on the unique key, because a FIPS code can be
     * another region's ISO code (VN 01 → 44, 44 → HN), also in cycles (CY 01 ↔ 04). The rows with a
     * FIPS code are therefore copied with the ISO code to the temporary table region_fix_tmp, deleted,
     * and inserted again. Rows that end up with the same key, e.g. because several FIPS codes map to
     * the same ISO code, are merged by summing their metrics.
     *
     * @return int Number of rows that needed to be converted
     */
    private function fixRegionCodes(string $table, array $config, int $contextId, int $submissionId, ?string $country = null): int
    {
        $alias = $config['alias'];
        $keyColumns = $config['keyColumns'];
        // restricts a query to the rows of this submission, or of this submission's country
        $inUnit = fn (Builder $query, string $prefix = '') => $query
            ->where("{$prefix}context_id", $contextId)
            ->where("{$prefix}submission_id", $submissionId)
            ->when($country !== null, fn (Builder $query) => $query->where("{$prefix}country", $country));

        // lock the rows, so that a job running at the same time cannot convert them a second time
        $ids = $inUnit(DB::table($table))
            ->whereNull('needs_review')
            ->lockForUpdate()
            ->pluck($config['idColumn']);
        if ($ids->isEmpty()) {
            return 0;
        }

        // copy the rows with a FIPS code to the temporary table, with the ISO code instead
        $selectColumns = array_map(
            fn (string $column) => $column === 'region' ? 'rm.iso as region' : "{$alias}.{$column}",
            [...$keyColumns, 'metric', 'metric_unique']
        );
        DB::table('region_fix_tmp')->insertUsing(
            [...$keyColumns, 'metric', 'metric_unique'],
            $inUnit(DB::table("{$table} as {$alias}"), "{$alias}.")
                ->join('region_mapping_tmp as rm', function ($join) use ($alias) {
                    $join->on("{$alias}.country", '=', 'rm.country')
                        ->on("{$alias}.region", '=', 'rm.fips');
                })
                ->whereNull("{$alias}.needs_review")
                ->select($selectColumns)
        );

        if ($inUnit(DB::table('region_fix_tmp'))->exists()) {
            // delete them, so that no old code is left that a new one could collide with
            $inUnit(DB::table($table))
                ->whereNull('needs_review')
                ->whereExists(function (Builder $query) use ($table) {
                    $query->select(DB::raw(1))
                        ->from('region_mapping_tmp as rm')
                        ->whereColumn('rm.country', "{$table}.country")
                        ->whereColumn('rm.fips', "{$table}.region");
                })
                ->delete();

            // Insert them again, merging rows with the same key. A row can also already exist with that key,
            // e.g. one inserted after the upgrade, so the metrics are added to it instead of failing.
            // On MySQL this also merges cities that the unique key considers the same (case- and
            // accent-insensitive collation, only the first 80 characters), but GROUP BY does not.
            $columns = implode(', ', $keyColumns);
            $selectSql = "SELECT {$columns}, SUM(metric) AS metric_tmp, SUM(metric_unique) AS metric_unique_tmp, 0 AS needs_review_tmp
                FROM region_fix_tmp
                WHERE context_id = ? AND submission_id = ?" . ($country !== null ? ' AND country = ?' : '') . "
                GROUP BY {$columns}";
            if (DB::connection() instanceof PostgresConnection) {
                $upsertSql = "INSERT INTO {$table} AS {$alias} ({$columns}, metric, metric_unique, needs_review)
                    {$selectSql}
                    ON CONFLICT ON CONSTRAINT {$config['uniqueKey']} DO UPDATE
                    SET metric = {$alias}.metric + excluded.metric, metric_unique = {$alias}.metric_unique + excluded.metric_unique";
            } else {
                $upsertSql = "INSERT INTO {$table} ({$columns}, metric, metric_unique, needs_review)
                    SELECT * FROM ({$selectSql}) AS t
                    ON DUPLICATE KEY UPDATE metric = metric + metric_tmp, metric_unique = metric_unique + metric_unique_tmp";
            }
            DB::statement($upsertSql, array_filter([$contextId, $submissionId, $country], fn ($value) => $value !== null));

            $inUnit(DB::table('region_fix_tmp'))->delete();
        }

        // the remaining rows have no FIPS code to convert
        $inUnit(DB::table($table))
            ->whereNull('needs_review')
            ->update(['needs_review' => 0]);

        return $ids->count();
    }

    /**
     * Create the table the rows to be converted are moved to.
     */
    private function createWorkTable(): void
    {
        Schema::create('region_fix_tmp', function (Blueprint $table) {
            $table->string('load_id', 50)->nullable();
            $table->bigInteger('context_id');
            $table->bigInteger('submission_id');
            $table->string('country', 2);
            $table->string('region', 3);
            $table->string('city', 255);
            $table->date('date')->nullable();
            $table->integer('month')->nullable();
            $table->integer('metric');
            $table->integer('metric_unique');
            $table->index(['submission_id', 'country']);
        });
    }

    /**
     * Drop the temporary indexes, the needs_review column, and the temporary tables.
     */
    private function cleanup(): void
    {
        foreach (self::TABLES as $table => $config) {
            Schema::table($table, function (Blueprint $t) use ($table, $config) {
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                $indexesFound = $sm->listTableIndexes($table);
                foreach ($config['tmpIndexes'] as $tmpIndex) {
                    if (array_key_exists($tmpIndex, $indexesFound)) {
                        $t->dropIndex($tmpIndex);
                    }
                }
            });

            if (Schema::hasColumn($table, 'needs_review')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('needs_review');
                });
            }
        }

        foreach (['region_mapping_tmp', 'region_fix_tmp'] as $tmpTable) {
            if (Schema::hasTable($tmpTable)) {
                Schema::drop($tmpTable);
            }
        }
    }
}
