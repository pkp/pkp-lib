<?php

/**
 * @file classes/services/PKPStatsGeoService.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsGeoService
 * @ingroup services
 *
 * @brief Helper class that encapsulates geographic statistics business logic
 */

namespace PKP\services;

use APP\services\queryBuilders\StatsGeoQueryBuilder;
use APP\statistics\StatisticsHelper;

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
    public function getQueryBuilder($args = []): StatsGeoQueryBuilder
    {
        $statsQB = new StatsGeoQueryBuilder();
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
     * Do usage stats data already exist for the given month
     *
     * @param string $month Month in the form YYYYMM
     */
    public function monthExists(string $month): bool
    {
        $statsQB = new StatsGeoQueryBuilder();
        return $statsQB->monthExists($month);
    }

    /**
     * Delete daily usage metrics for a month
     *
     * @param string $month Month in the form YYYYMM
     */
    public function deleteDailyMetrics(string $month): void
    {
        $statsQB = new StatsGeoQueryBuilder();
        $statsQB->deleteDailyMetrics($month);
    }

    /**
     * Delete monthly usage metrics for a month
     *
     * @param string $month Month in the form YYYYMM
     */
    public function deleteMonthlyMetrics(string $month): void
    {
        $statsQB = new StatsGeoQueryBuilder();
        $statsQB->deleteMonthlyMetrics($month);
    }

    /**
     * Aggregate daily usage metrics by a month
     *
     * @param string $month Month in the form YYYYMM
     */
    public function addMonthlyMetrics(string $month): void
    {
        $statsQB = new StatsGeoQueryBuilder();
        $statsQB->addMonthlyMetrics($month);
    }
}
