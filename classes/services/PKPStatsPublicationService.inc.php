<?php

/**
 * @file classes/services/PKPStatsPublicationService.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsPublicationService
 * @ingroup services
 *
 * @brief Helper class that encapsulates publication statistics business logic
 */

namespace PKP\services;

use APP\core\Application;
use PKP\core\PKPString;
use PKP\plugins\HookRegistry;
use PKP\statistics\PKPStatisticsHelper;

class PKPStatsPublicationService
{
    /**
     * A callback to be used with array_map() to return all
     * submission IDs from the records.
     */
    public function filterSubmissionIds(object $record): int
    {
        return $record->submission_id;
    }

    /**
     * A callback to be used with array_filter() to return
     * records for a PDF file.
     */
    public function filterRecordPdf(object $record): bool
    {
        return $record->assoc_type == Application::ASSOC_TYPE_SUBMISSION_FILE && $record->file_type == PKPStatisticsHelper::STATISTICS_FILE_TYPE_PDF;
    }

    /**
     * A callback to be used with array_filter() to return
     * records for a HTML file.
     */
    public function filterRecordHtml(object $record): bool
    {
        return $record->assoc_type == Application::ASSOC_TYPE_SUBMISSION_FILE && $record->file_type == PKPStatisticsHelper::STATISTICS_FILE_TYPE_HTML;
    }

    /**
     * A callback to be used with array_filter() to return
     * records for Other (than PDF and HTML) file.
     */
    public function filterRecordOther(object $record): bool
    {
        return $record->assoc_type == Application::ASSOC_TYPE_SUBMISSION_FILE && $record->file_type == PKPStatisticsHelper::STATISTICS_FILE_TYPE_OTHER;
    }

    /**
     * A callback to be used with array_filter() to return
     * records for absract.
     */
    public function filterRecordAbstract(object $record): bool
    {
        return $record->assoc_type == Application::ASSOC_TYPE_SUBMISSION;
    }

    /**
     * Get total count of submissions matching the given parameters
     */
    public function getTotalCount(array $args): int
    {
        $defaultArgs = $this->getDefaultArgs();
        $args = array_merge($defaultArgs, $args, ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION, Application::ASSOC_TYPE_SUBMISSION_FILE]]);
        unset($args['count']);
        unset($args['offset']);
        $metricsQB = $this->getQueryBuilder($args);

        HookRegistry::call('StatsPublication::getTotalCount::queryBuilder', [&$metricsQB, $args]);

        $groupBy = [PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID];
        $metricsQB = $metricsQB->getSum($groupBy);

        return $metricsQB->get()->count();
    }

    /**
     * Get total metrics for every submission, ordered by metrics, return just the requested offset
     */
    public function getTotalMetrics(array $args): array
    {
        $defaultArgs = $this->getDefaultArgs();
        $args = array_merge($defaultArgs, $args, ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION, Application::ASSOC_TYPE_SUBMISSION_FILE], 'orderDirection' => PKPStatisticsHelper::STATISTICS_ORDER_DESC]);
        $metricsQB = $this->getQueryBuilder($args);

        HookRegistry::call('StatsPublication::getTotalMetrics::queryBuilder', [&$metricsQB, $args]);

        $groupBy = [PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID];
        $metricsQB = $metricsQB->getSum($groupBy);

        $args['orderDirection'] === PKPStatisticsHelper::STATISTICS_ORDER_ASC ? 'asc' : 'desc';
        $metricsQB->orderBy(PKPStatisticsHelper::STATISTICS_METRIC, $args['orderDirection']);

        if (isset($args['count'])) {
            $metricsQB->limit($args['count']);
            if (isset($args['offset'])) {
                $metricsQB->offset($args['offset']);
            }
        }

        return $metricsQB->get()->toArray();
    }

    /**
     * Get metrics by type (abstract, pdf, html, other) for a submission
     * Assumes that the submission ID is provided in parameters
     */
    public function getMetricsByType(array $args): array
    {
        $defaultArgs = $this->getDefaultArgs();
        $args = array_merge($defaultArgs, $args, ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION, Application::ASSOC_TYPE_SUBMISSION_FILE]]);
        $metricsQB = $this->getQueryBuilder($args);

        HookRegistry::call('StatsPublication::getMetricsByType::queryBuilder', [&$metricsQB, $args]);

        // get abstract, pdf, html and other views for the submission
        $groupBy = [PKPStatisticsHelper::STATISTICS_DIMENSION_ASSOC_TYPE, PKPStatisticsHelper::STATISTICS_DIMENSION_FILE_TYPE];

        $metricsQB = $metricsQB->getSum($groupBy);
        return $metricsQB->get()->toArray();
    }

    /**
     * Get total count of submisison files matching the given parameters
     */
    public function getTotalFilesCount(array $args): int
    {
        $defaultArgs = $this->getDefaultArgs();
        $args = array_merge($defaultArgs, $args, ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION_FILE, Application::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER]]);
        unset($args['count']);
        unset($args['offset']);
        $metricsQB = $this->getQueryBuilder($args);

        HookRegistry::call('StatsPublication::getTotalFilesCount::queryBuilder', [&$metricsQB, $args]);

        $groupBy = [PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID, PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_FILE_ID];
        $metricsQB = $metricsQB->getSum($groupBy);

        return $metricsQB->get()->count();
    }

    /**
     * Get total metrics for every submission file, ordered by metrics, return just the requested offset
     */
    public function getTotalFilesMetrics(array $args): array
    {
        $defaultArgs = $this->getDefaultArgs();
        $args = array_merge($defaultArgs, $args, ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION_FILE, Application::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER]]);
        $metricsQB = $this->getQueryBuilder($args);

        HookRegistry::call('StatsPublication::getFilesMetrics::queryBuilder', [&$metricsQB, $args]);

        $groupBy = [PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID, PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_FILE_ID];
        $metricsQB = $metricsQB->getSum($groupBy);

        $args['orderDirection'] === PKPStatisticsHelper::STATISTICS_ORDER_ASC ? 'asc' : 'desc';
        $metricsQB->orderBy(PKPStatisticsHelper::STATISTICS_METRIC, $args['orderDirection']);

        if (isset($args['count'])) {
            $metricsQB->limit($args['count']);
            if (isset($args['offset'])) {
                $metricsQB->offset($args['offset']);
            }
        }

        return $metricsQB->get()->toArray();
    }

    /**
     * Get the sum of a set of metrics broken down by day or month
     *
     * @param string $timelineInterval STATISTICS_DIMENSION_MONTH or STATISTICS_DIMENSION_DAY
     * @param array $args Filter the records to include. See self::getQueryBuilder()
     *
     */
    public function getTimeline(string $timelineInterval, array $args = []): array
    {
        $defaultArgs = array_merge($this->getDefaultArgs(), ['orderDirection' => PKPStatisticsHelper::STATISTICS_ORDER_ASC]);
        $args = array_merge($defaultArgs, $args);
        $timelineQB = $this->getQueryBuilder($args);

        HookRegistry::call('StatsPublication::getTimeline::queryBuilder', [&$timelineQB, $args]);

        $timelineQO = $timelineQB
            ->getSum([$timelineInterval])
            ->orderBy($timelineInterval, $args['orderDirection']);

        $result = $timelineQO->get();

        $dateValues = [];
        foreach ($result as $row) {
            $row = (array) $row;
            $date = $row[$timelineInterval];
            if ($timelineInterval === PKPStatisticsHelper::STATISTICS_DIMENSION_MONTH) {
                $date = substr($date, 0, 7);
            }
            $dateValues[$date] = (int) $row['metric'];
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

    /**
     * Get default parameters
     */
    public function getDefaultArgs(): array
    {
        return [
            'dateStart' => PKPStatisticsHelper::STATISTICS_EARLIEST_DATE,
            'dateEnd' => date('Y-m-d', strtotime('yesterday')),

            // Require a context to be specified to prevent unwanted data leakage
            // if someone forgets to specify the context.
            'contextIds' => [\PKP\core\PKPApplication::CONTEXT_ID_NONE],
        ];
    }

    /**
     * Get a QueryBuilder object with the passed args
     */
    public function getQueryBuilder(array $args = []): \PKP\services\queryBuilders\PKPStatsPublicationQueryBuilder
    {
        $statsQB = new \PKP\services\queryBuilders\PKPStatsPublicationQueryBuilder();
        $statsQB
            ->filterByContexts($args['contextIds'])
            ->before($args['dateEnd'])
            ->after($args['dateStart']);

        if (!empty(($args['submissionIds']))) {
            $statsQB->filterBySubmissions($args['submissionIds']);
        }

        if (!empty($args['assocTypes'])) {
            $statsQB->filterByAssocTypes($args['assocTypes']);
        }

        if (!empty($args['fileTypes'])) {
            $statsQB->filterByFileTypes(($args['fileTypes']));
        }

        if (!empty(($args['representationIds']))) {
            $statsQB->filterByRepresentations($args['representationIds']);
        }

        if (!empty(($args['submissionFileIds']))) {
            $statsQB->filterBySubmissionFiles($args['submissionFileIds']);
        }

        HookRegistry::call('StatsPublication::queryBuilder', [&$statsQB, $args]);

        return $statsQB;
    }
}
