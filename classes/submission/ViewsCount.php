<?php
/**
 * @file classes/submission/ViewsCount.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ViewsCount
 *
 * @brief trait to use with a collector to build a query for counting submissions/reviewAssignments in a view
 */

namespace PKP\submission;

use APP\submission\Collector as AppCollector;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait ViewsCount
{
    /**
     * Builds a single query to retrieve submissions count for all dashboard views
     * @param Collection [
     *   Dashboard view unique ID => Submission Collector with filters applied
     * ]
     */
    public static function getViewsCountBuilder(Collection $keyCollectorPair): Builder
    {
        $q = DB::query();
        $keyCollectorPair->each(function(AppCollector $collector, string $key) use ($q) {
            // Get query builder from a collector instance, override a select statement to retrieve submissions count instead of submissions data
            $subQuery = $collector->getQueryBuilder()->select([])->selectRaw(
                'COUNT('. $this->dao->table . '.' . $this->dao->primaryKeyColumn . ')'
            );
            $q->selectSub($subQuery, $key);
        });
        return $q;
    }
}
