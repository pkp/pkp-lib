<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I6782_OrphanedMetrics.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I6782_OrphanedMetrics
 * @brief Migrate metrics data from objects that do not exist any more and from assoc types that are not considered in the upgrade into the temporary table.
 * These entries will be copied back and stay in the table metrics_old, s. I6782_CleanOldMetrics.
 * Consider only metric_type ojs/ops/omp::counter here, because these entries will be removed during the upgrade.
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

abstract class I6782_OrphanedMetrics extends Migration
{
    private const ASSOC_TYPE_SUBMISSION = 0x0100009;
    private const ASSOC_TYPE_SUBMISSION_FILE = 0x0000203;
    private const ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER = 0x0000213;

    abstract protected function getMetricType(): string;
    abstract protected function getContextAssocType(): int;
    abstract protected function getContextTable(): string;
    abstract protected function getContextKeyField(): string;
    abstract protected function getRepresentationTable(): string;
    abstract protected function getRepresentationKeyField(): string;

    /**
     * Get assoc types that will be considered in the upgrade
     */
    protected function getAssocTypesToMigrate(): array
    {
        return [
            self::ASSOC_TYPE_SUBMISSION,
            self::ASSOC_TYPE_SUBMISSION_FILE,
            self::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER,
        ];
    }

    /**
     * Run the migration.
     *
     * assoc_object_type, assoc_object_id, and pkp_section_id will not be considered here, because they are not relevant for the upgrade
     */
    public function up(): void
    {
        $this->createMetricsTmpTable();

        $metricsColumns = Schema::getColumnListing('metrics_tmp');

        // Clean orphaned metrics context IDs. These IDs can be deleted.
        // as assoc_id
        $orphanedIds = DB::table('metrics AS m')->leftJoin($this->getContextTable() . ' AS c', 'm.assoc_id', '=', 'c.' . $this->getContextKeyField())->where('m.assoc_type', '=', $this->getContextAssocType())->where('m.metric_type', '=', $this->getMetricType())->whereNull('c.' . $this->getContextKeyField())->distinct()->pluck('m.assoc_id');
        foreach ($orphanedIds as $contextId) {
            $this->_installer->log("Removing stats for context {$contextId} because no context with that ID could be found.");
            DB::table('metrics')->where('assoc_type', '=', $this->getContextAssocType())->where('assoc_id', '=', $contextId)->delete();
        }
        // as context_id
        $orphanedIds = DB::table('metrics AS m')->leftJoin($this->getContextTable() . ' AS c', 'm.context_id', '=', 'c.' . $this->getContextKeyField())->where('m.metric_type', '=', $this->getMetricType())->whereNull('c.' . $this->getContextKeyField())->distinct()->pluck('m.context_id');
        foreach ($orphanedIds as $contextId) {
            $this->_installer->log("Removing stats for context {$contextId} because no context with that ID could be found.");
            DB::table('metrics')->where('context_id', '=', $contextId)->delete();
        }

        // Clean orphaned metrics submission IDs
        // as submission_id
        $orphanedIds = DB::table('metrics AS m')->leftJoin('submissions AS s', 'm.submission_id', '=', 's.submission_id')->whereNotNull('m.submission_id')->whereNull('s.submission_id')->distinct()->pluck('m.submission_id');
        $orphandedSubmissions = DB::table('metrics')->select($metricsColumns)->whereIn('submission_id', $orphanedIds)->where('metric_type', '=', $this->getMetricType());
        DB::table('metrics_tmp')->insertUsing($metricsColumns, $orphandedSubmissions);
        DB::table('metrics')->whereIn('submission_id', $orphanedIds)->delete();

        // as assoc_id
        $orphanedIds = DB::table('metrics AS m')->leftJoin('submissions AS s', 'm.assoc_id', '=', 's.submission_id')->where('m.assoc_type', '=', self::ASSOC_TYPE_SUBMISSION)->whereNull('s.submission_id')->distinct()->pluck('m.assoc_id');
        $orphandedSubmissionsAssocId = DB::table('metrics')->select($metricsColumns)->where('assoc_type', '=', self::ASSOC_TYPE_SUBMISSION)->whereIn('assoc_id', $orphanedIds)->where('metric_type', '=', $this->getMetricType());
        DB::table('metrics_tmp')->insertUsing($metricsColumns, $orphandedSubmissionsAssocId);
        DB::table('metrics')->where('assoc_type', '=', self::ASSOC_TYPE_SUBMISSION)->whereIn('assoc_id', $orphanedIds)->delete();

        // Clean orphaned metrics submission file IDs
        $orphanedIds = DB::table('metrics AS m')->leftJoin('submission_files AS sf', 'm.assoc_id', '=', 'sf.submission_file_id')->where('m.assoc_type', '=', self::ASSOC_TYPE_SUBMISSION_FILE)->whereNull('sf.submission_file_id')->distinct()->pluck('m.assoc_id');
        $orphandedSubmissionFiles = DB::table('metrics')->select($metricsColumns)->where('assoc_type', '=', self::ASSOC_TYPE_SUBMISSION_FILE)->whereIn('assoc_id', $orphanedIds)->where('metric_type', '=', $this->getMetricType());
        DB::table('metrics_tmp')->insertUsing($metricsColumns, $orphandedSubmissionFiles);
        DB::table('metrics')->where('assoc_type', '=', self::ASSOC_TYPE_SUBMISSION_FILE)->whereIn('assoc_id', $orphanedIds)->delete();

        // Clean orphaned metrics submission supp file IDs
        $orphanedIds = DB::table('metrics AS m')->leftJoin('submission_files AS sf', 'm.assoc_id', '=', 'sf.submission_file_id')->where('m.assoc_type', '=', self::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER)->whereNull('sf.submission_file_id')->distinct()->pluck('m.assoc_id');
        $orphandedSubmissionSuppFiles = DB::table('metrics')->select($metricsColumns)->where('assoc_type', '=', self::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER)->whereIn('assoc_id', $orphanedIds)->where('metric_type', '=', $this->getMetricType());
        DB::table('metrics_tmp')->insertUsing($metricsColumns, $orphandedSubmissionSuppFiles);
        DB::table('metrics')->where('assoc_type', '=', self::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER)->whereIn('assoc_id', $orphanedIds)->delete();

        // Clean orphaned metrics representation IDs
        $orphanedIds = DB::table('metrics AS m')->leftJoin($this->getRepresentationTable() . ' AS r', 'm.representation_id', '=', 'r.' . $this->getRepresentationKeyField())->whereNotNull('m.representation_id')->whereNull('r.' . $this->getRepresentationKeyField())->distinct()->pluck('m.representation_id');
        $orphandedRepresentations = DB::table('metrics')->select($metricsColumns)->whereIn('representation_id', $orphanedIds)->where('metric_type', '=', $this->getMetricType());
        DB::table('metrics_tmp')->insertUsing($metricsColumns, $orphandedRepresentations);
        DB::table('metrics')->whereIn('representation_id', $orphanedIds)->delete();

        // Copy assoc types that will not be migrated to the metrics_tmp table
        $orphanedAssocTypes = DB::table('metrics AS m')->select($metricsColumns)->whereNotIn('m.assoc_type', $this->getAssocTypesToMigrate())->where('metric_type', '=', $this->getMetricType());
        DB::table('metrics_tmp')->insertUsing($metricsColumns, $orphanedAssocTypes);
    }

    public function createMetricsTmpTable()
    {
        Schema::create('metrics_tmp', function (Blueprint $table) {
            $table->string('load_id', 255);
            $table->bigInteger('context_id');
            $table->bigInteger('pkp_section_id')->nullable();
            $table->bigInteger('assoc_object_type')->nullable();
            $table->bigInteger('assoc_object_id')->nullable();
            $table->bigInteger('submission_id')->nullable();
            $table->bigInteger('representation_id')->nullable();
            $table->bigInteger('assoc_type');
            $table->bigInteger('assoc_id');
            $table->string('day', 8)->nullable();
            $table->string('month', 6)->nullable();
            $table->smallInteger('file_type')->nullable();
            $table->string('country_id', 2)->nullable();
            $table->string('region', 2)->nullable();
            $table->string('city', 255)->nullable();
            $table->string('metric_type', 255);
            $table->integer('metric');
            $table->index(['load_id'], 'metrics_tmp_load_id');
            $table->index(['metric_type', 'context_id'], 'metrics_tmp_metric_type_context_id');
            $table->index(['metric_type', 'submission_id', 'assoc_type'], 'metrics_tmp_metric_type_submission_id_assoc_type');
            $table->index(['metric_type', 'context_id', 'assoc_type', 'assoc_id'], 'metrics_tmp_metric_type_submission_id_assoc');
        });
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
