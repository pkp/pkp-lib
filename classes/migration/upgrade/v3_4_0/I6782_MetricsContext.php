<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I6782_MetricsContext.inc.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I6782_MetricsContext
 * @brief Migrate context stats data from the old DB table metrics into the new DB table metrics_context.
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

abstract class I6782_MetricsContext extends Migration
{
    abstract protected function getMetricType(): string;
    abstract protected function getContextAssocType(): int;

    /**
     * Run the migration.
     */
    public function up(): void
    {
        $dayFormatSql = "DATE_FORMAT(STR_TO_DATE(m.day, '%Y%m%d'), '%Y-%m-%d')";
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            $dayFormatSql = "to_date(m.day, 'YYYYMMDD')";
        }

        // The not existing foreign keys should already be moved to the metrics_tmp in I6782_OrphanedMetrics
        $selectContextMetrics = DB::table('metrics as m')
            ->select(DB::raw("m.load_id, m.assoc_id, {$dayFormatSql}, m.metric"))
            ->where('m.assoc_type', '=', $this->getContextAssocType())
            ->where('m.metric_type', '=', $this->getMetricType());
        DB::table('metrics_context')->insertUsing(['load_id', 'context_id', 'date', 'metric'], $selectContextMetrics);
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
