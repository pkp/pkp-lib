<?php
/**
 * @file classes/core/interfaces/CollectorInterface.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CollectorInterface
 *
 * @brief An interface describing the methods an Collector class must implement.
 */

namespace PKP\core\interfaces;

use Illuminate\Database\Query\Builder;

interface CollectorInterface
{
    /**
     * Get the configured query builder
     *
     * This returns an instance of Laravel's query builder. Use this
     * to execute queries on the entity's table that do not already
     * have a query method.
     *
     * The following example shows how to use this method after applying
     * query conditions. In this example, the query is used to get
     * only the date of the last three announcements:
     *
     * ```php
     * $dates = Repo::announcemennt()
     *   ->filterByContextIds([$contextId])
     *   ->getQueryBuilder()
     *   ->limit(3)
     *   ->pluck('date_posted');
     * ```
     *
     * See: https://laravel.com/docs/8.x/queries
     */
    public function getQueryBuilder(): Builder;
}
