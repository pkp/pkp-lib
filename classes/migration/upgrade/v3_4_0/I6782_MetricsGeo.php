<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I6782_MetricsGeo.inc.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I6782_MetricsGeo
 * @brief Migrate submission stats Geo data from the old DB table metrics into the new DB table metrics_submission_geo_daily, then aggregate monthly.
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

abstract class I6782_MetricsGeo extends Migration
{
    private const ASSOC_TYPE_SUBMISSION = 0x0100009;
    private const ASSOC_TYPE_SUBMISSION_FILE = 0x0000203;
    private const ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER = 0x0000213;

    abstract protected function getMetricType(): string;

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

        // Migrate Geo metrics -- no matter if the Geo usage stats are currently enabled
        // fix wrong entries in the DB table metrics
        // do all this first in order for groupBy to function properly
        DB::table('metrics')->where('city', '')->update(['city' => null]);
        DB::table('metrics')->where('region', '')->orWhere('region', '0')->update(['region' => null]);
        DB::table('metrics')->where('country_id', '')->update(['country_id' => null]);
        // in the GeoIP Legacy databases, several country codes were included that don't represent countries
        DB::table('metrics')->whereIn('country_id', ['AP', 'EU', 'A1', 'A2'])->update(['country_id' => null, 'region' => null, 'city' => null]);
        // some regions are missing the leading '0'
        DB::table('metrics')->update(['region' => DB::raw("LPAD(region, 2, '0')")]);

        // Insert into metrics_submission_geo_daily table
        // metric = total views = abstracts + galley and supp files
        $selectGeoMetrics = DB::table('metrics as m')
            ->select(DB::raw("m.load_id, m.context_id, m.submission_id, COALESCE(m.country_id, ''), COALESCE(m.region, ''), COALESCE(m.city, ''), {$dayFormatSql} as mday, SUM(m.metric), 0"))
            ->whereIn('m.assoc_type', [self::ASSOC_TYPE_SUBMISSION, self::ASSOC_TYPE_SUBMISSION_FILE, self::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER])
            ->where('m.metric_type', '=', $this->getMetricType())
            ->where(function ($q) {
                $q->whereNotNull('m.country_id')
                    ->orWhereNotNull('m.region')
                    ->orWhereNotNull('m.city');
            })
            ->groupBy(DB::raw('m.load_id, m.context_id, m.submission_id, m.country_id, m.region, m.city, mday'));
        DB::table('metrics_submission_geo_daily')->insertUsing(['load_id', 'context_id', 'submission_id', 'country', 'region', 'city', 'date', 'metric', 'metric_unique'], $selectGeoMetrics);

        // Migrate to metrics_submission_geo_monthly table
        $monthFormatSql = "CAST(DATE_FORMAT(gd.date, '%Y%m') AS UNSIGNED)";
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            $monthFormatSql = "to_char(gd.date, 'YYYYMM')::integer";
        }
        // use the table metrics_submission_geo_daily instead of table metrics to calculate the monthly numbers
        $selectSubmissionGeoDaily = DB::table('metrics_submission_geo_daily as gd')
            ->select(DB::raw("gd.context_id, gd.submission_id, COALESCE(gd.country, ''), COALESCE(gd.region, ''), COALESCE(gd.city, ''), {$monthFormatSql} as gdmonth, SUM(gd.metric), SUM(gd.metric_unique)"))
            ->groupBy(DB::raw('gd.context_id, gd.submission_id, gd.country, gd.region, gd.city, gdmonth'));
        DB::table('metrics_submission_geo_monthly')->insertUsing(['context_id', 'submission_id', 'country', 'region', 'city', 'month', 'metric', 'metric_unique'], $selectSubmissionGeoDaily);
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
