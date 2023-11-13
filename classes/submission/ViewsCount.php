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
 * @brief interface to use with a collector to build a query for counting submissions/reviewAssignments in a view
 */

namespace PKP\submission;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use PKP\core\interfaces\CollectorInterface;

interface ViewsCount
{
    /**
     * Builds a single query to retrieve submissions count for all dashboard views
     * @param Collection<string, CollectorInterface> $keyCollectorPair [
     *   Dashboard view unique ID => Submission Collector with filters applied
     * ]
     */
    public static function getViewsCountBuilder(Collection $keyCollectorPair): Builder;
}
