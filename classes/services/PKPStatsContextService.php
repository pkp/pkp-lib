<?php

/**
 * @file classes/services/PKPStatsContextService.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsContextService
 *
 * @ingroup services
 *
 * @brief Helper class that encapsulates context statistics business logic
 */

namespace PKP\services;

use APP\statistics\StatisticsHelper;
use PKP\services\queryBuilders\PKPStatsContextQueryBuilder;

class PKPStatsContextService
{
    use PKPStatsServiceTrait;

    /**
     * Get a count of all contexts with stats that match the request arguments
     */
    public function getCount(array $args): int
    {
        $defaultArgs = $this->getDefaultArgs();
        $args = array_merge($defaultArgs, $args);
        unset($args['count']);
        unset($args['offset']);
        $metricsQB = $this->getQueryBuilder($args);
        return $metricsQB->getContextIds()->get()->count();
    }

    /**
     * Get the contexts with total stats that match the request arguments
     */
    public function getTotals(array $args): array
    {
        $defaultArgs = $this->getDefaultArgs();
        $args = array_merge($defaultArgs, $args);
        $metricsQB = $this->getQueryBuilder($args);

        $groupBy = [StatisticsHelper::STATISTICS_DIMENSION_CONTEXT_ID];
        $metricsQB = $metricsQB->getSum($groupBy);

        $orderDirection = $args['orderDirection'] === StatisticsHelper::STATISTICS_ORDER_ASC ? 'asc' : 'desc';
        $metricsQB->orderBy(StatisticsHelper::STATISTICS_METRIC, $orderDirection);
        return $metricsQB->get()->toArray();
    }

    /**
     * Get the total views for a context.
     */
    public function getTotal(int $contextId, ?string $dateStart, ?string $dateEnd): int
    {
        $defaultArgs = $this->getDefaultArgs();
        $args = [
            'contextIds' => [$contextId],
            'dateStart' => $dateStart ?? $defaultArgs['dateStart'],
            'dateEnd' => $dateEnd ?? $defaultArgs['dateEnd'],
        ];
        $metricsQB = $this->getQueryBuilder($args);
        $metrics = $metricsQB->getSum([])->value('metric');
        return $metrics ? $metrics : 0;
        ;
    }

    /**
     * Get default parameters
     */
    public function getDefaultArgs(): array
    {
        return [
            'dateStart' => StatisticsHelper::STATISTICS_EARLIEST_DATE,
            'dateEnd' => date('Y-m-d', strtotime('yesterday')),
        ];
    }

    /**
     * Get a QueryBuilder object with the passed args
     */
    public function getQueryBuilder(array $args = []): PKPStatsContextQueryBuilder
    {
        $statsQB = new PKPStatsContextQueryBuilder();
        $statsQB
            ->before($args['dateEnd'])
            ->after($args['dateStart']);

        if (!empty($args['contextIds'])) {
            $statsQB->filterByContexts($args['contextIds']);
        }

        if (isset($args['count'])) {
            $statsQB->limit($args['count']);
            if (isset($args['offset'])) {
                $statsQB->offset($args['offset']);
            }
        }

        return $statsQB;
    }
}
