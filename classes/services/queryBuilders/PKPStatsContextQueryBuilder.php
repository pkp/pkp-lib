<?php

/**
 * @file classes/services/queryBuilders/PKPStatsContextQueryBuilder.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsContextQueryBuilder
 *
 * @ingroup query_builders
 *
 * @brief Helper class to construct a query to fetch context stats records from the
 *  metrics_context table.
 */

namespace PKP\services\queryBuilders;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use PKP\plugins\Hook;
use PKP\statistics\PKPStatisticsHelper;

class PKPStatsContextQueryBuilder extends PKPStatsQueryBuilder
{
    /**
     * Get contexts IDs
     */
    public function getContextIds(): Builder
    {
        return $this->_getObject()
            ->select([PKPStatisticsHelper::STATISTICS_DIMENSION_CONTEXT_ID])
            ->groupBy(PKPStatisticsHelper::STATISTICS_DIMENSION_CONTEXT_ID);
    }

    /**
     * @copydoc PKPStatsQueryBuilder::_getObject()
     *
     * @hook StatsContext::queryObject [[&$q, $this]]
     */
    protected function _getObject(): Builder
    {
        $q = DB::table('metrics_context');

        if (!empty($this->contextIds)) {
            $q->whereIn(PKPStatisticsHelper::STATISTICS_DIMENSION_CONTEXT_ID, $this->contextIds);
        }

        $q->whereBetween(PKPStatisticsHelper::STATISTICS_DIMENSION_DATE, [$this->dateStart, $this->dateEnd]);

        if ($this->limit > 0) {
            $q->limit($this->limit);
            if ($this->offset > 0) {
                $q->offset($this->offset);
            }
        }

        Hook::call('StatsContext::queryObject', [&$q, $this]);

        return $q;
    }
}
