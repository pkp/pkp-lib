<?php

declare(strict_types=1);

/**
 * @file classes/db/DatabaseServiceProvider.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DatabaseServiceProvider
 * @ingroup db
 *
 * @brief Registers/initializes database related utilities.
 */

namespace PKP\db;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     */
    public function register(): void
    {
        // Adds the safeCount() method to the query builder, which works with queries that have a GROUP BY clause and is supposed to have a better performance by dropping the SELECT/ORDER BY clauses
        Builder::macro('safeCount', function (): int {
            // Discard the ORDER BY and enclose the query in a sub-query to avoid miscounting rows in the presence of a GROUP BY
            $run = fn (Builder $query) => DB::table(DB::raw("({$query->reorder()->toSql()}) AS query"))->mergeBindings($query)->count();
            try {
                /** @var Builder $query */
                $query = clone $this;
                // Discard the SELECT if the query doesn't have a UNION
                return $run(empty($query->unions) ? $query->select(DB::raw('0')) : $query);
            } catch (Exception $e) {
                // Retry using a fail-proof query, as dropping the SELECT might fail in the presence of a GROUP BY with an alias
                return $run(clone $this);
            }
        });
    }
}
