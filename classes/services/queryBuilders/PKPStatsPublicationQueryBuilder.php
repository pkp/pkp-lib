<?php

/**
 * @file classes/services/queryBuilders/PKPStatsPublicationQueryBuilder.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsPublicationQueryBuilder
 * @ingroup query_builders
 *
 * @brief Helper class to construct a query to fetch stats records from the
 *  metrics_submission table.
 */

namespace PKP\services\queryBuilders;

use APP\core\Application;
use APP\submission\Submission;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use PKP\plugins\Hook;
use PKP\statistics\PKPStatisticsHelper;

abstract class PKPStatsPublicationQueryBuilder extends PKPStatsQueryBuilder
{
    /**
     *Include records for one of these object types:
     * ASSOC_TYPE_SUBMISSION, ASSOC_TYPE_SUBMISSION_FILE, ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER
     */
    protected array $assocTypes = [];

    /** Include records for these file types: STATISTICS_FILE_TYPE_* */
    protected array $fileTypes = [];

    /** Include records for these sections/series */
    protected array $pkpSectionIds = [];

    /** Include records for these submissions */
    protected array $submissionIds = [];

    /** Include records for these representations (galley or publication format) */
    protected array $representationIds = [];

    /** Include records for these submission files */
    protected array $submissionFileIds = [];


    /**
     * Set the sections/series to get records for
     */
    public function filterByPKPSections(array $pkpSectionIds): self
    {
        $this->pkpSectionIds = $pkpSectionIds;
        return $this;
    }

    /**
     * Set the submissions to get records for
     */
    public function filterBySubmissions(array $submissionIds): self
    {
        $this->submissionIds = $submissionIds;
        return $this;
    }

    /**
     * Set the representations to get records for
     */
    public function filterByRepresentations(array $representationIds): self
    {
        $this->representationIds = $representationIds;
        return $this;
    }

    /**
     * Set the files to get records for
     */
    public function filterBySubmissionFiles(array $submissionFileIds): self
    {
        $this->submissionFileIds = $submissionFileIds;
        return $this;
    }

    /**
     * Set the assocTypes to get records for
     */
    public function filterByAssocTypes(array $assocTypes): self
    {
        $this->assocTypes = $assocTypes;
        return $this;
    }

    /**
     * Set the galley file type to get records for
     */
    public function filterByFileTypes(array $fileTypes): self
    {
        $this->fileTypes = $fileTypes;
        return $this;
    }

    /**
     * Get submission IDs
     */
    public function getSubmissionIds(): Builder
    {
        return $this->_getObject()
            ->select(['metrics_submission.' . PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID])
            ->distinct();
    }

    /**
     * @copydoc PKPStatsQueryBuilder::getSum()
     */
    public function getSum(array $groupBy = []): Builder
    {
        $groupBy = array_map(function ($column) {
            return $column == PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID ? 'metrics_submission.' . $column : $column;
        }, $groupBy);
        return parent::getSum($groupBy);
    }

    /**
     * Consider/add application specific queries
     */
    abstract protected function _getAppSpecificQuery(Builder &$q): void;

    /**
     * @copydoc PKPStatsQueryBuilder::_getObject()
     */
    protected function _getObject(): Builder
    {
        $q = DB::table('metrics_submission');

        if (!empty($this->contextIds)) {
            $q->whereIn(PKPStatisticsHelper::STATISTICS_DIMENSION_CONTEXT_ID, $this->contextIds);
        }

        if (!empty($this->submissionIds)) {
            $q->whereIn('metrics_submission.' . PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID, $this->submissionIds);
        }

        if (!empty($this->assocTypes)) {
            $q->whereIn(PKPStatisticsHelper::STATISTICS_DIMENSION_ASSOC_TYPE, $this->assocTypes);
        }

        if (!empty($this->fileTypes)) {
            $q->whereIn(PKPStatisticsHelper::STATISTICS_DIMENSION_FILE_TYPE, $this->fileTypes);
        }

        if (!empty($this->representationIds)) {
            $q->whereIn(PKPStatisticsHelper::STATISTICS_DIMENSION_REPRESENTATION_ID, $this->representationIds);
        }

        if (!empty($this->submissionFileIds)) {
            $q->whereIn(PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_FILE_ID, $this->submissionFileIds);
        }

        $q->whereBetween(PKPStatisticsHelper::STATISTICS_DIMENSION_DATE, [$this->dateStart, $this->dateEnd]);

        if (!empty($this->pkpSectionIds)) {
            $sectionColumn = 'p.section_id';
            if (Application::get()->getName() == 'omp') {
                $sectionColumn = 'p.series_id';
            }
            $sectionSubmissionIds = DB::table('publications as p')->select('p.submission_id')->distinct()
                ->from('publications as p')
                ->where('p.status', Submission::STATUS_PUBLISHED)
                ->whereIn($sectionColumn, $this->pkpSectionIds);
            $q->joinSub($sectionSubmissionIds, 'ss', function ($join) {
                $join->on('metrics_submission.' . PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID, '=', 'ss.submission_id');
            });
        }

        $this->_getAppSpecificQuery($q);

        if ($this->limit > 0) {
            $q->limit($this->limit);
            if ($this->offset > 0) {
                $q->offset($this->offset);
            }
        }

        Hook::call('StatsPublication::queryObject', [&$q, $this]);

        return $q;
    }
}
