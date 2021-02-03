<?php

/**
 * @file classes/services/queryBuilders/interfaces/EntityQueryBuilderInterface.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EntityQueryBuilderInterface
 * @ingroup services_query_builders
 *
 * @brief An interface that defines required methods for
 *   a QueryBuilder that retrieves one of the application's
 *   entities.
 */
namespace PKP\Services\QueryBuilders\Interfaces;

interface EntityQueryBuilderInterface {

	/**
	 * Get a count of the number of rows that match the select
	 * conditions configured in this query builder.
	 *
	 * @return int
	 */
	public function getCount();

	/**
	 * Get a list of ids that match the select conditions
	 * configured in this query builder.
	 *
	 * @return array
	 */
	public function getIds();

	/**
	 * Get a query builder with the applied select, where and
	 * join clauses based on builder's configuration
	 *
	 * This returns an instance of Laravel's query builder.
	 *
	 * Call the `get` method on a query builder to return an array
	 * of matching rows.
	 *
	 * ```php
	 * $qb = new \PKP\Services\QueryBuilders\PublicationQueryBuilder();
	 * $result = $qb
	 *   ->filterByContextIds(1)
	 *   ->getQuery()
	 *   ->get();
	 * ```
	 *
	 * Or use the query builder to retrieve objects from a DAO.
	 * This example retrieves the first 20 matching Publications.
	 *
	 * ```php
	 * $qo = $qb
	 *   ->filterByContextIds(1)
	 *   ->getQuery();
	 * $result = DAORegistry::getDAO('PublicationDAO')->retrieveRange(
	 *   $qo->toSql(),
	 *   $qo->getBindings(),
	 *   new DBResultRange(20, null, 0);
	 * );
	 * $queryResults = new DAOResultFactory($result, $publicationDao, '_fromRow');
	 * $iteratorOfObjects = $queryResults->toIterator();
	 * ```
	 *
	 * Laravel's other query builder methods, such as `first`
	 * and `pluck`, can also be used.
	 *
	 * ```
	 * $qb = new \PKP\Services\QueryBuilders\PublicationQueryBuilder();
	 * $result = $qb
	 *   ->filterByContextIds(1)
	 *   ->getQuery()
	 *   ->first();
	 * ```
	 *
	 * See: https://laravel.com/docs/5.5/queries
	 *
	 * @return Illuminate\Database\Query\Builder
	 */
	public function getQuery();
}
