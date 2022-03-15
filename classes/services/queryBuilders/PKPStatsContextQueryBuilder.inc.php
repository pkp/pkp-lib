<?php

/**
 * @file classes/services/queryBuilders/PKPStatsContextQueryBuilder.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsContextQueryBuilder
 * @ingroup query_builders
 *
 * @brief Helper class to construct a query to fetch context stats records from the
 *  metrics_context table.
 */

namespace PKP\services\queryBuilders;

use Illuminate\Support\Facades\DB;
use PKP\plugins\HookRegistry;
use PKP\statistics\PKPStatisticsHelper;

class PKPStatsContextQueryBuilder extends PKPStatsQueryBuilder
{
    /**
     * @copydoc PKPStatsQueryBuilder::_getObject()
     */
    protected function _getObject(): \Illuminate\Database\Query\Builder
    {
        $q = DB::table('metrics_context');

        if (!empty($this->contextIds)) {
            $q->whereIn(PKPStatisticsHelper::STATISTICS_DIMENSION_CONTEXT_ID, $this->contextIds);
        }

        $q->whereBetween(PKPStatisticsHelper::STATISTICS_DIMENSION_DATE, [$this->dateStart, $this->dateEnd]);

        HookRegistry::call('StatsContext::queryObject', [&$q, $this]);

        return $q;
    }
}
