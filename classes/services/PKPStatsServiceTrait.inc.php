<?php

/**
 * @file classes/services/PKPStatsServiceTrait.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsServiceTrait
 * @ingroup services
 *
 * @brief Helper class that encapsulates publication statistics business logic
 */

namespace PKP\services;

use APP\core\Application;
use PKP\core\PKPString;
use PKP\plugins\HookRegistry;
use PKP\statistics\PKPStatisticsHelper;

trait PKPStatsServiceTrait
{
    /**
     * Get the sum of a set of metrics broken down by day or month
     *
     * @param string $timelineInterval STATISTICS_DIMENSION_MONTH or STATISTICS_DIMENSION_DAY
     * @param array $args Filter the records to include. See self::getQueryBuilder()
     *
     */
    public function getTimeline(string $timelineInterval, array $args = []): array
    {
        $defaultArgs = array_merge($this->getDefaultArgs());
        $args = array_merge($defaultArgs, $args);
        $timelineQB = $this->getQueryBuilder($args);

        HookRegistry::call('Stats::getTimeline::queryBuilder', [&$timelineQB, $args]);

        $orderDirection = 'asc';
        if (array_key_exists('orderDirection', $args)) {
            $orderDirection = strtolower($args['orderDirection']);
        }
        $timelineQO = $timelineQB
            ->getSum([$timelineInterval])
            ->orderBy($timelineInterval, $orderDirection);

        $result = $timelineQO->get();

        $dateValues = [];
        foreach ($result as $row) {
            $date = $row->$timelineInterval;
            if ($timelineInterval === PKPStatisticsHelper::STATISTICS_DIMENSION_MONTH) {
                $date = substr($date, 0, 7);
            }
            $dateValues[$date] = $row->metric;
        }

        $timeline = $this->getEmptyTimelineIntervals($args['dateStart'], $args['dateEnd'], $timelineInterval);

        $timeline = array_map(function ($entry) use ($dateValues) {
            foreach ($dateValues as $date => $value) {
                if ($entry['date'] === $date) {
                    $entry['value'] = $value;
                    break;
                }
            }
            return $entry;
        }, $timeline);

        return $timeline;
    }

    /**
     * Get all time segments (months or days) between the start and end date
     * with empty values.
     *
     * @param string $timelineInterval STATISTICS_DIMENSION_MONTH or STATISTICS_DIMENSION_DAY
     *
     * @return array of time segments in ASC order
     */
    public function getEmptyTimelineIntervals(string $startDate, string $endDate, string $timelineInterval): array
    {
        if ($timelineInterval === PKPStatisticsHelper::STATISTICS_DIMENSION_MONTH) {
            $dateFormat = 'Y-m';
            $labelFormat = 'F Y';
            $interval = 'P1M';
        } elseif ($timelineInterval === PKPStatisticsHelper::STATISTICS_DIMENSION_DAY) {
            $dateFormat = 'Y-m-d';
            $labelFormat = PKPString::convertStrftimeFormat(Application::get()->getRequest()->getContext()->getLocalizedDateFormatLong());
            $interval = 'P1D';
        }

        $startDate = new \DateTime($startDate);
        $endDate = new \DateTime($endDate);

        $timelineIntervals = [];
        while ($startDate->format($dateFormat) <= $endDate->format($dateFormat)) {
            $timelineIntervals[] = [
                'date' => $startDate->format($dateFormat),
                'label' => date($labelFormat, $startDate->getTimestamp()),
                'value' => 0,
            ];
            $startDate->add(new \DateInterval($interval));
        }

        return $timelineIntervals;
    }
}
