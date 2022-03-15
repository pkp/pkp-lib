<?php

/**
 * @file classes/services/queryBuilders/PKPStatsQueryBuilder.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsQueryBuilder
 * @ingroup query_builders
 *
 * @brief Basis class for statistics query builders.
 */

namespace PKP\services\queryBuilders;

use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\statistics\PKPStatisticsHelper;

abstract class PKPStatsQueryBuilder
{
    /** Include records for these contexts */
    protected array $contextIds = [];

    /** Include records from this date or before. Default: yesterday's date */
    protected string $dateEnd;

    /** Include records from this date or after. Default: STATISTICS_EARLIEST_DATE */
    protected string $dateStart;

    /**
     * Set the contexts to get records for
     */
    public function filterByContexts(array|int $contextIds): self
    {
        $this->contextIds = is_array($contextIds) ? $contextIds : [$contextIds];
        return $this;
    }

    /**
     * Set the date before which to get records
     *
     * @param string $dateEnd YYYY-MM-DD
     *
     */
    public function before(string $dateEnd): self
    {
        $this->dateEnd = $dateEnd;
        return $this;
    }

    /**
     * Set the date after which to get records
     *
     * @param string $dateStart YYYY-MM-DD
     *
     */
    public function after(string $dateStart): self
    {
        $this->dateStart = $dateStart;
        return $this;
    }

    /**
     * Get the sum of all matching records
     *
     * Use this method to get the total X views. Pass a
     * $groupBy argument to get the total X views for each
     * object, grouped by one or more columns.
     *
     * @param array $groupBy One or more columns to group by
     *
     */
    public function getSum(array $groupBy = []): \Illuminate\Database\Query\Builder
    {
        $selectColumns = $groupBy;
        $selectColumns = $this->getSelectColumns($selectColumns);

        $q = $this->_getObject();
        // Build the select and group by clauses.
        if (!empty($selectColumns)) {
            $q->select($selectColumns);
            if (!empty($groupBy)) {
                $q->groupBy($groupBy);
            }
        }
        $q->addSelect(DB::raw('SUM(metric) AS metric'));

        return $q;
    }

    /**
     * Generate a query object based on the configured conditions.
     *
     * Public methods should call this method to set up the query
     * object and apply any additional selection, grouping and
     * ordering conditions.
     */
    abstract protected function _getObject(): \Illuminate\Database\Query\Builder;

    /**
     * Get appropriate SQL code for columns in the select part of the query
     */
    protected function getSelectColumns(array $selectColumns): array
    {
        if (in_array(PKPStatisticsHelper::STATISTICS_DIMENSION_YEAR, $selectColumns)
            || in_array(PKPStatisticsHelper::STATISTICS_DIMENSION_MONTH, $selectColumns)
            || in_array(PKPStatisticsHelper::STATISTICS_DIMENSION_DAY, $selectColumns)) {
            foreach ($selectColumns as $i => $selectColumn) {
                if ($selectColumn == PKPStatisticsHelper::STATISTICS_DIMENSION_YEAR) {
                    if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
                        $selectColumns[$i] = DB::raw("date_trunc('year',date) AS year");
                    } else {
                        $selectColumns[$i] = DB::raw("date_format(date, '%Y-01-01') AS year");
                    }
                    break;
                } elseif ($selectColumn == PKPStatisticsHelper::STATISTICS_DIMENSION_MONTH) {
                    if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
                        $selectColumns[$i] = DB::raw("date_trunc('month',date) AS month");
                    } else {
                        $selectColumns[$i] = DB::raw("date_format(date, '%Y-%m-01') AS month");
                    }
                    break;
                } elseif ($selectColumn == PKPStatisticsHelper::STATISTICS_DIMENSION_DAY) {
                    $selectColumns[$i] = DB::raw('date AS day');
                    break;
                }
            }
        }
        return $selectColumns;
    }
}
