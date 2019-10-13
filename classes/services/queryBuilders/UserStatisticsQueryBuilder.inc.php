<?php
/**
 * @file classes/services/QueryBuilders/UserStatisticsQueryBuilder.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UserStatisticsQueryBuilder
 * @ingroup query_builders
 *
 * @brief User statistics query builder
 */

namespace PKP\Services\QueryBuilders;

use Illuminate\Database\Capsule\Manager as Capsule;

class UserStatisticsQueryBuilder extends BaseQueryBuilder {
	/** @var int context ID filter */
	private $_contextId = null;

	/** @var DateTimeInterface Start date filter */
	private $_start = null;

	/** @var DateTimeInterface End date filter */
	private $_end = null;

	/**
	 * Filter the statistics by context
	 * @param $id int Context Id
	 * @return \PKP\Services\QueryBuilders\UserStatisticsQueryBuilder
	 */
	public function withContext(?int $id) : self
	{
		$this->_contextId = $id;
		return $this;
	}

	/**
	 * Filter the statistics by date range
	 * @param $start DateTimeInterface
	 * @param $end DateTimeInterface
	 * @return \PKP\Services\QueryBuilders\UserStatisticsQueryBuilder
	 */
	public function withDateRange(?\DateTimeInterface $start, ?\DateTimeInterface $end) : self
	{
		$this->_start = $start;
		$this->_end = $end;
		return $this;
	}

	/**
	 * Build the query
	 * @return Illuminate\Support\Collection Query object
	 */
	public function build() : \Illuminate\Database\Query\Builder
	{
		$capsule = $this->capsule;

		// Retrieve an unique list of users and their roles
		$distinctUserRolesQuery = $capsule
			->table('user_user_groups AS uug')
			->join('users AS u', 'u.user_id', 'uug.user_id')
			->join('user_groups AS ug', 'ug.user_group_id', 'uug.user_group_id')
			// Group by user_id and role_id to remove duplicates
			->groupBy('uug.user_id', 'ug.role_id')
			->select('ug.role_id');

		// Add filter by context
		if ($this->_contextId) {
			$distinctUserRolesQuery->where('ug.context_id', $this->_contextId);
		}

		// Add filter by date range
		if ($this->_start || $this->_end) {
			$distinctUserRolesQuery->whereRaw(
				'u.date_registered BETWEEN COALESCE(?, u.date_registered) AND COALESCE(?, u.date_registered)',
				[$this->_start, $this->_end]
			);
		}

		// Group by role and extract totals
		$distinctUserRolesQuery = $capsule
			->table(Capsule::raw('(' . $distinctUserRolesQuery->toSql() . ') AS roles'))
			->select(['roles.role_id', Capsule::raw('COUNT(0) AS total')])
			->groupBy('roles.role_id')
			->mergeBindings($distinctUserRolesQuery);

		// Retrieve the total amount of registrations, basically all users that have any assigned role (orphan users, without a role, are ignored)
		$usersQuery = $capsule
			->table('users AS u')
			->whereExists(function ($query) {
				$query
					->from('user_user_groups AS uug')
					->leftJoin('user_groups AS ug', 'ug.user_group_id', 'uug.user_group_id')
					->where('uug.user_id', Capsule::raw('u.user_id'));

				// Add filter by context
				if ($this->_contextId) {
					$query->where('ug.context_id', $this->_contextId);
				}

				// Add filter by date range
				if ($this->_start || $this->_end) {
					$query->whereRaw(
						'u.date_registered BETWEEN COALESCE(?, u.date_registered) AND COALESCE(?, u.date_registered)',
						[$this->_start, $this->_end]
					);
				}
			})
			->select([Capsule::raw('0 AS role_id'), Capsule::raw('COUNT(0) AS total')]);

		// Join total registrations with registrations per role queries
		$userStatisticsQuery = $distinctUserRolesQuery->union($usersQuery);

		// Extract the amount of years covered in the dataset
		$yearsQuery = $capsule->getConnection()->query()->selectRaw('
			EXTRACT(YEAR FROM COALESCE(?, CURRENT_TIMESTAMP))
			- EXTRACT(YEAR FROM
				COALESCE(
					?,
					('
						. $capsule
							->table('users AS u')
							->whereNotNull('u.date_registered')
							->orderBy('u.date_registered')
							->limit(1)
							->select('u.date_registered')
							->toSql()
					. '),
					CURRENT_TIMESTAMP
				)
			) + 1 AS count',
			[$this->_start, $this->_end]
		);

		// Final query
		$query = $capsule
			->table(Capsule::raw('(' . $userStatisticsQuery->toSql() . ') AS statistics'))
			->select(['statistics.*', Capsule::raw('statistics.total / years.count AS average')])
			->join(Capsule::raw('(' . $yearsQuery->toSql() . ') AS years'), Capsule::raw('1'), '=', Capsule::raw('1'))
			->groupBy(['role_id', 'statistics.total', 'years.count']);

		// Add app-specific query statements
		\HookRegistry::call(implode('::', [__CLASS__, __METHOD__, 'queryObject']), array(&$query, $this));

		return self::addBindings($query, $userStatisticsQuery, $yearsQuery);
	}
}
