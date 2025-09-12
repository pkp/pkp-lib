<?php

/**
 * @file jobs/ror/ProcessRorCsv.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ProcessRorCsv
 *
 * @brief Job to import ROR dataset in chunks/batches
 */

namespace PKP\jobs\ror;

use Throwable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use PKP\jobs\BaseJob;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\task\UpdateRorRegistryDataset;
use Illuminate\Database\Schema\Blueprint;
use PKP\scheduledTask\ScheduledTaskHelper;

class ProcessRorCsv extends BaseJob
{
    /**
     * Indicate if the job should be marked as failed on timeout.
     */
    public bool $failOnTimeout = false;

    use Batchable;

    public function __construct(
        protected string $pathCsv,
        protected int $startRow,
        protected int $endRow,
        protected array $dataMapping,
        protected array $dataMappingIndex,
        protected string $noLocale,
        protected string $temporaryTable,
        protected string $scheduledTaskLogFilePath
    )
    {
        parent::__construct();
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware()
    {
        return [
            (new WithoutOverlapping("{$this->pathCsv}:{$this->startRow}-{$this->endRow}"))->expireAfter(60), // 1-minute lock
        ];
    }

    public function handle()
    {
        try {
            UpdateRorRegistryDataset::writeToExecutionLogFile(
                "Importing batch chunk {$this->startRow}-{$this->endRow} starting",
                $this->scheduledTaskLogFilePath,
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE
            );

            $this->createTemporaryTable();

            $batchRows = [];
            $currentRow = 0;
            $isHeader = true;

            if (($handle = fopen($this->pathCsv, 'r')) !== false) {
                while (($rowCsv = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
                    if ($isHeader) {
                        foreach ($this->dataMapping as $keyDB => $keyCsv) {
                            $this->dataMappingIndex[$keyDB] = array_search($keyCsv, $rowCsv, true);
                        }
                        $isHeader = false;
                        continue;
                    }

                    $currentRow++;
                    if ($currentRow < $this->startRow) {
                        continue;
                    }
                    if ($currentRow > $this->endRow) {
                        break;
                    }

                    $batchRows[] = $this->processRow($rowCsv);
                }
                fclose($handle);

                if (!empty($batchRows)) {
                    $this->processBatch($batchRows);
                }
            }

            $this->dropTemporaryTable();

            UpdateRorRegistryDataset::writeToExecutionLogFile(
                "Importing batch chunk {$this->startRow}-{$this->endRow} completed successfully",
                $this->scheduledTaskLogFilePath,
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_COMPLETED
            );
        } catch (Throwable $e) {
            UpdateRorRegistryDataset::writeToExecutionLogFile(
                "Importing batch chunk {$this->startRow}-{$this->endRow} failed: {$e->getMessage()}",
                $this->scheduledTaskLogFilePath,
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
            );

            $this->dropTemporaryTable();

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        $this->dropTemporaryTable();

        UpdateRorRegistryDataset::writeToExecutionLogFile(
            "Importing batch chunk job has failed : {$exception->getMessage()}",
            $this->scheduledTaskLogFilePath,
            ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
        );
    }

    protected function processRow(array $row): array
    {
        $ror = $row[$this->dataMappingIndex['ror']];
        $displayLocale = (!empty($row[$this->dataMappingIndex['displayLocale']]))
            ? $row[$this->dataMappingIndex['displayLocale']]
            : $this->noLocale;
        $isActive = (strtolower($row[$this->dataMappingIndex['isActive']]) === 'active') ? 1 : 0;
        $searchPhrase = $ror;

        $namesIn = $row[$this->dataMappingIndex['names']];
        $namesOut = [];
        if (!empty($namesIn)) {
            $names = array_map('trim', explode(';', $namesIn));
            for ($i = 0; $i < count($names); $i++) {
                $name = array_map('trim', explode(':', $names[$i]));
                if (count($name) === 2) {
                    $namesOut[$name[0]] = trim($name[1]);
                    $searchPhrase .= ' ' . $namesOut[$name[0]];
                }
            }
        }

        if (empty($namesOut[$displayLocale])) {
            $namesOut[$displayLocale] = $row[$this->dataMappingIndex['displayName']];
        }

        return [
            'ror' => $ror,
            'displayLocale' => $displayLocale,
            'isActive' => $isActive,
            'searchPhrase' => trim($searchPhrase),
            'name' => $namesOut,
        ];
    }

    protected function processBatch(array $rows): void
    {
        try {
            $values = [];
            
            foreach ($rows as $row) {
                foreach ($row['name'] as $locale => $name) {
                    $values[] = [
                        'ror' => $row['ror'],
                        'display_locale' => $row['displayLocale'],
                        'is_active' => $row['isActive'],
                        'search_phrase' => $row['searchPhrase'],
                        'locale' => $locale,
                        'setting_name' => 'name',
                        'setting_value' => $name
                    ];
                }
            }
            DB::table($this->temporaryTable)->insert($values);

            $values = DB::table($this->temporaryTable . ' as tmp')
                ->select('tmp.ror', 'tmp.display_locale', 'tmp.is_active', 'tmp.search_phrase')
                ->distinct()
                ->leftJoin('rors as r', 'tmp.ror', '=', 'r.ror')
                ->get()
                ->map(function ($item) {
                    return (array)$item;
                })
                ->all();
            DB::table('rors')->upsert(
                $values,
                ['ror'],
                ['display_locale', 'is_active', 'search_phrase']
            );

            $orphanedSettings = DB::table('ror_settings AS rs')
                ->select('rs.ror_setting_id')
                ->join('rors as r', 'rs.ror_id', '=', 'r.ror_id')
                ->join($this->temporaryTable . ' as tmp1', 'tmp1.ror', '=', 'r.ror')
                ->leftJoin($this->temporaryTable . ' AS tmp2', function ($join) {
                    $join->on('tmp2.ror', '=', 'tmp1.ror')
                        ->on('tmp2.locale', '=', 'rs.locale')
                        ->on('tmp2.setting_name', '=', 'rs.setting_name');
                })
                ->whereNull('tmp2.locale')
                ->distinct()
                ->pluck('rs.ror_setting_id');
            DB::table('ror_settings')->whereIn('ror_setting_id', $orphanedSettings)->delete();

            $values = DB::table($this->temporaryTable . ' as tmp')
                ->select('r.ror_id', 'tmp.locale', 'tmp.setting_name', 'tmp.setting_value')
                ->join('rors as r', 'tmp.ror', '=', 'r.ror')
                ->leftJoin('ror_settings as rs', function ($join) {
                    $join->on('r.ror_id', '=', 'rs.ror_id')
                        ->on('rs.locale', '=', 'tmp.locale')
                        ->on('rs.setting_name', '=', 'tmp.setting_name');
                })
                ->distinct()
                ->get()
                ->map(function ($item) {
                    return (array)$item;
                })
                ->all();
            
            DB::table('ror_settings')->upsert(
                $values, 
                ['ror_id', 'locale', 'setting_name'], 
                ['setting_value']
            );

        } catch (Throwable $e) {
            UpdateRorRegistryDataset::writeToExecutionLogFile(
                "Processing batch chunk {$this->startRow}-{$this->endRow} failed: {$e->getMessage()}",
                $this->scheduledTaskLogFilePath,
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
            );
            throw $e;
        }
    }

    /**
     * Create temporary table
     */
    protected function createTemporaryTable(): void
    {
        // May be for some unforseen reason, table temp left so need to drop it before creating it
        if (Schema::hasTable($this->temporaryTable)) {
            $this->dropTemporaryTable();
        }

        Schema::create($this->temporaryTable, function (Blueprint $table) {
            $table->comment('Rors temporary table');
            $table->string('ror')->nullable(false);
            $table->string('display_locale', 28)->nullable(false);
            $table->smallInteger('is_active')->nullable(false)->default(1);
            $table->mediumText('search_phrase')->nullable();
            $table->string('locale', 28)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();
            $table->unique(['ror', 'locale', 'setting_name'], $this->temporaryTable . '_unique');
        });
    }

    /**
     * Drop temporary table
     */
    protected function dropTemporaryTable(): void
    {
        Schema::dropIfExists($this->temporaryTable);
    }
}
