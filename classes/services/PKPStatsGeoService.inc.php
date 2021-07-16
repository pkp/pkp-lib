<?php

/**
 * @file classes/services/PKPStatsGeoService.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsGeoService
 * @ingroup services
 *
 * @brief Helper class that encapsulates geographic statistics business logic
 */

namespace PKP\services;

use APP\statistics\StatisticsHelper;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\services\queryBuilders\PKPStatsGeoQueryBuilder;

class PKPStatsGeoService
{
    use PKPStatsServiceTrait;

    /**
     * Get a count of all countries, regions or cities with stats that match the request arguments
     *
     * @param string $scale Possible values are:
     *  StatisticsHelper::STATISTICS_DIMENSION_CITY
     *  StatisticsHelper::STATISTICS_DIMENSION_REGION
     *  StatisticsHelper::STATISTICS_DIMENSION_COUNTRY
     */
    public function getCount(array $args, string $scale): int
    {
        $defaultArgs = $this->getDefaultArgs();
        $args = array_merge($defaultArgs, $args);
        unset($args['count']);
        unset($args['offset']);
        $metricsQB = $this->getQueryBuilder($args);

        $groupBy = [];
        if ($scale == StatisticsHelper::STATISTICS_DIMENSION_CITY) {
            $groupBy = [StatisticsHelper::STATISTICS_DIMENSION_COUNTRY, StatisticsHelper::STATISTICS_DIMENSION_REGION, StatisticsHelper::STATISTICS_DIMENSION_CITY];
        } elseif ($scale == StatisticsHelper::STATISTICS_DIMENSION_REGION) {
            $groupBy = [StatisticsHelper::STATISTICS_DIMENSION_COUNTRY, StatisticsHelper::STATISTICS_DIMENSION_REGION];
        } elseif ($scale == StatisticsHelper::STATISTICS_DIMENSION_COUNTRY) {
            $groupBy = [StatisticsHelper::STATISTICS_DIMENSION_COUNTRY];
        }

        return $metricsQB->getGeoData($groupBy)->get()->count();
    }

    /**
     * Get the countries, regions or cities with total stats that match the request arguments
     *
     * @param string $scale Possible values are:
     *  StatisticsHelper::STATISTICS_DIMENSION_CITY
     *  StatisticsHelper::STATISTICS_DIMENSION_REGION
     *  StatisticsHelper::STATISTICS_DIMENSION_COUNTRY
     */
    public function getTotals(array $args, string $scale): array
    {
        $defaultArgs = $this->getDefaultArgs();
        $args = array_merge($defaultArgs, $args);
        $metricsQB = $this->getQueryBuilder($args);

        $groupBy = [];
        if ($scale == StatisticsHelper::STATISTICS_DIMENSION_CITY) {
            $groupBy = [StatisticsHelper::STATISTICS_DIMENSION_COUNTRY, StatisticsHelper::STATISTICS_DIMENSION_REGION, StatisticsHelper::STATISTICS_DIMENSION_CITY];
        } elseif ($scale == StatisticsHelper::STATISTICS_DIMENSION_REGION) {
            $groupBy = [StatisticsHelper::STATISTICS_DIMENSION_COUNTRY, StatisticsHelper::STATISTICS_DIMENSION_REGION];
        } elseif ($scale == StatisticsHelper::STATISTICS_DIMENSION_COUNTRY) {
            $groupBy = [StatisticsHelper::STATISTICS_DIMENSION_COUNTRY];
        }
        $metricsQB = $metricsQB->getSum($groupBy);

        $orderDirection = $args['orderDirection'] === StatisticsHelper::STATISTICS_ORDER_ASC ? 'asc' : 'desc';
        $metricsQB->orderBy(StatisticsHelper::STATISTICS_METRIC, $orderDirection);
        return $metricsQB->get()->toArray();
    }

    /**
     * Get default parameters
     */
    public function getDefaultArgs(): array
    {
        return [
            'dateStart' => StatisticsHelper::STATISTICS_EARLIEST_DATE,
            'dateEnd' => date('Y-m-d', strtotime('yesterday')),

            // Require a context to be specified to prevent unwanted data leakage
            // if someone forgets to specify the context.
            'contextIds' => [\PKP\core\PKPApplication::CONTEXT_ID_NONE],
        ];
    }

    /**
     * Get a QueryBuilder object with the passed args
     */
    public function getQueryBuilder($args = []): PKPStatsGeoQueryBuilder
    {
        $statsQB = new PKPStatsGeoQueryBuilder();
        $statsQB
            ->filterByContexts($args['contextIds'])
            ->before($args['dateEnd'])
            ->after($args['dateStart']);

        if (!empty(($args['pkpSectionIds']))) {
            $statsQB->filterByPKPSections($args['pkpSectionIds']);
        }
        if (!empty($args['submissionIds'])) {
            $statsQB->filterBySubmissions($args['submissionIds']);
        }
        if (!empty($args['countries'])) {
            $statsQB->filterByCountries($args['countries']);
        }
        if (!empty($args['regions'])) {
            $statsQB->filterByRegions($args['regions']);
        }
        if (!empty($args['cities'])) {
            $statsQB->filterByCities($args['cities']);
        }
        if (isset($args['count'])) {
            $statsQB->limit($args['count']);
            if (isset($args['offset'])) {
                $statsQB->offset($args['offset']);
            }
        }
        return $statsQB;
    }

    /**
     * Delete daily usage metrics for a month
     *
     * @param string $month Month in the form YYYYMM
     */
    public function deleteDailyMetrics(string $month): void
    {
        // Construct the SQL part depending on the DB
        $monthFormatSql = "DATE_FORMAT(date, '%Y%m')";
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            $monthFormatSql = "to_char(date, 'YYYYMM')";
        }
        DB::table('metrics_submission_geo_daily')->where(DB::raw($monthFormatSql), '=', $month)->delete();
    }

    /**
     * Delete monthly usage metrics for a month
     *
     * @param string $month Month in the form YYYYMM
     */
    public function deleteMonthlyMetrics(string $month): void
    {
        DB::table('metrics_submission_geo_monthly')->where('month', $month)->delete();
    }

    /**
     * Aggregate daily usage metrics by a month
     *
     * @param string $month Month in the form YYYYMM
     */
    public function addMonthlyMetrics(string $month): void
    {
        // Construct the SQL part depending on the DB
        $monthFormatSql = "CAST(DATE_FORMAT(gd.date, '%Y%m') AS UNSIGNED)";
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            $monthFormatSql = "to_char(gd.date, 'YYYYMM')::integer";
        }
        $selectSubmissionGeoDaily = DB::table('metrics_submission_geo_daily as gd')
            ->select(DB::raw("gd.context_id, gd.submission_id, COALESCE(gd.country, ''), COALESCE(gd.region, ''), COALESCE(gd.city, ''), {$monthFormatSql} as gdmonth, SUM(gd.metric), SUM(gd.metric_unique)"))
            ->whereRaw("{$monthFormatSql} = ?", [$month])
            ->groupBy(DB::raw('gd.context_id, gd.submission_id, gd.country, gd.region, gd.city, gdmonth'));
        DB::table('metrics_submission_geo_monthly')->insertUsing(['context_id', 'submission_id', 'country', 'region', 'city', 'month', 'metric', 'metric_unique'], $selectSubmissionGeoDaily);
    }
}
