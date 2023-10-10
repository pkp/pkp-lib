<?php

/**
 * @file classes/services/PKPStatsPublicationService.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsPublicationService
 *
 * @ingroup services
 *
 * @brief Helper class that encapsulates publication statistics business logic
 */

namespace PKP\services;

use APP\core\Application;
use APP\services\queryBuilders\StatsPublicationQueryBuilder;
use PKP\plugins\Hook;
use PKP\statistics\PKPStatisticsHelper;

abstract class PKPStatsPublicationService
{
    use PKPStatsServiceTrait;

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
     * records for abstract.
     */
    public function filterRecordAbstract(object $record): bool
    {
        return $record->assoc_type == Application::ASSOC_TYPE_SUBMISSION;
    }

    /**
     * A callback to be used with array_filter() to return
     * records for supplementary file.
     */
    public function filterRecordSuppFile(object $record): bool
    {
        return $record->assoc_type == Application::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER;
    }

    /**
     * Get a count of all submissions with stats that match the request arguments
     */
    public function getCount(array $args): int
    {
        $defaultArgs = $this->getDefaultArgs();
        $args = array_merge(
            $defaultArgs,
            ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION, Application::ASSOC_TYPE_SUBMISSION_FILE]],
            $args
        );
        unset($args['count']);
        unset($args['offset']);
        $metricsQB = $this->getQueryBuilder($args);

        Hook::call('StatsPublication::getCount::queryBuilder', [&$metricsQB, $args]);

        return $metricsQB->getSubmissionIds()->get()->count();
    }

    /**
     * Get the submissions with total stats that match the request arguments
     */
    public function getTotals(array $args): array
    {
        $defaultArgs = $this->getDefaultArgs();
        $args = array_merge(
            $defaultArgs,
            ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION, Application::ASSOC_TYPE_SUBMISSION_FILE],
                'orderDirection' => PKPStatisticsHelper::STATISTICS_ORDER_DESC],
            $args
        );
        $metricsQB = $this->getQueryBuilder($args);

        Hook::call('StatsPublication::getTotals::queryBuilder', [&$metricsQB, $args]);

        $groupBy = [PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID];
        $metricsQB = $metricsQB->getSum($groupBy);

        $orderDirection = $args['orderDirection'] === PKPStatisticsHelper::STATISTICS_ORDER_ASC ? 'asc' : 'desc';
        $metricsQB->orderBy(PKPStatisticsHelper::STATISTICS_METRIC, $orderDirection);
        return $metricsQB->get()->toArray();
    }

    /**
     * Get metrics by type (abstract, pdf, html, other) for a submission
     * Assumes that the submission ID is provided in parameters
     */
    public function getTotalsByType(int $submissionId, int $contextId, ?string $dateStart, ?string $dateEnd): array
    {
        $defaultArgs = $this->getDefaultArgs();
        $args = [
            'submissionIds' => [$submissionId],
            'contextIds' => [$contextId],
            'dateStart' => $dateStart ?? $defaultArgs['dateStart'],
            'dateEnd' => $dateEnd ?? $defaultArgs['dateEnd'],
            'assocTypes' => [Application::ASSOC_TYPE_SUBMISSION, Application::ASSOC_TYPE_SUBMISSION_FILE, Application::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER]
        ];
        $metricsQB = $this->getQueryBuilder($args);

        Hook::call('StatsPublication::getTotalsByType::queryBuilder', [&$metricsQB, $args]);

        // get abstract, pdf, html and other views for the submission
        $groupBy = [PKPStatisticsHelper::STATISTICS_DIMENSION_ASSOC_TYPE, PKPStatisticsHelper::STATISTICS_DIMENSION_FILE_TYPE];

        $metricsQB = $metricsQB->getSum($groupBy);
        $metricsByType = $metricsQB->get()->toArray();

        $abstractViews = $pdfViews = $htmlViews = $otherViews = $suppFileViews = 0;
        $abstractRecord = array_filter($metricsByType, $this->filterRecordAbstract(...));
        if (!empty($abstractRecord)) {
            $abstractViews = (int) current($abstractRecord)->metric;
        }
        $pdfRecord = array_filter($metricsByType, $this->filterRecordPdf(...));
        if (!empty($pdfRecord)) {
            $pdfViews = (int) current($pdfRecord)->metric;
        }
        $htmlRecord = array_filter($metricsByType, $this->filterRecordHtml(...));
        if (!empty($htmlRecord)) {
            $htmlViews = (int) current($htmlRecord)->metric;
        }
        $otherRecord = array_filter($metricsByType, $this->filterRecordOther(...));
        if (!empty($otherRecord)) {
            $otherViews = (int) current($otherRecord)->metric;
        }
        $suppFileRecord = array_filter($metricsByType, $this->filterRecordSuppFile(...));
        if (!empty($suppFileRecord)) {
            $suppFileViews = (int) current($suppFileRecord)->metric;
        }

        return [
            'abstract' => $abstractViews,
            'pdf' => $pdfViews,
            'html' => $htmlViews,
            'other' => $otherViews,
            'suppFileViews' => $suppFileViews
        ];
    }

    /**
     * Get a count of all submission files with stats that match the request arguments
     */
    public function getFilesCount(array $args): int
    {
        $defaultArgs = $this->getDefaultArgs();
        $args = array_merge(
            $defaultArgs,
            ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION_FILE, Application::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER]],
            $args
        );
        unset($args['count']);
        unset($args['offset']);
        $metricsQB = $this->getQueryBuilder($args);

        Hook::call('StatsPublication::getFilesCount::queryBuilder', [&$metricsQB, $args]);

        $groupBy = [PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID, PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_FILE_ID];
        $metricsQB = $metricsQB->getSum($groupBy);

        return $metricsQB->get()->count();
    }

    /**
     * Get the submission files with total stats that match the request arguments
     */
    public function getFilesTotals(array $args): array
    {
        $defaultArgs = $this->getDefaultArgs();
        $args = array_merge(
            $defaultArgs,
            ['assocTypes' => [Application::ASSOC_TYPE_SUBMISSION_FILE, Application::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER]],
            $args
        );
        $metricsQB = $this->getQueryBuilder($args);

        Hook::call('StatsPublication::getFilesTotals::queryBuilder', [&$metricsQB, $args]);

        $groupBy = [PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID, PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_FILE_ID, PKPStatisticsHelper::STATISTICS_DIMENSION_ASSOC_TYPE];
        $metricsQB = $metricsQB->getSum($groupBy);

        $orderDirection = $args['orderDirection'] === PKPStatisticsHelper::STATISTICS_ORDER_ASC ? 'asc' : 'desc';
        $metricsQB->orderBy(PKPStatisticsHelper::STATISTICS_METRIC, $orderDirection);

        return $metricsQB->get()->toArray();
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
     * Consider/add application specific QB filters
     */
    protected function getAppSpecificFilters(StatsPublicationQueryBuilder &$statsQB, array $args = []): void
    {
    }

    /**
     * Get a QueryBuilder object with the passed args
     */
    public function getQueryBuilder(array $args = []): StatsPublicationQueryBuilder
    {
        $statsQB = new StatsPublicationQueryBuilder();
        $statsQB
            ->filterByContexts($args['contextIds'])
            ->before($args['dateEnd'])
            ->after($args['dateStart']);

        if (!empty(($args['pkpSectionIds']))) {
            $statsQB->filterByPKPSections($args['pkpSectionIds']);
        }

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

        $this->getAppSpecificFilters($statsQB, $args);

        if (isset($args['count'])) {
            $statsQB->limit($args['count']);
            if (isset($args['offset'])) {
                $statsQB->offset($args['offset']);
            }
        }

        Hook::call('StatsPublication::queryBuilder', [&$statsQB, $args]);

        return $statsQB;
    }
}
