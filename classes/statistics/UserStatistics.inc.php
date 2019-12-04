<?php

/**
* @file classes/statistics/UserStatistics.inc.php
*
* Copyright (c) 2013-2019 Simon Fraser University
* Copyright (c) 2003-2019 John Willinsky
* Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
*
* @class UserStatistics
* @ingroup statistics
*
* @brief Class responsible to keep submission statistics retrieved through the EditorialStatisticsService
*
*/

namespace PKP\Statistics;

class UserStatistics {
	/** @var object An array containing all the harvested data */
	private $_data = [];

	/**
	 * Constructor
	 * @param $data iterable Must receive the result from the UserStatisticsQueryBuilder query builder
	 */
	public function __construct(iterable $data) {
		foreach ($data as $row) {
			$this->_data[$row->role_id] = $row;
		}
	}

	/**
	 * Retrieve the amount of registered users for the given role
	 * @param $roleId int A role ID constant
	 * @return int
	 */
	public function getRegistrationsByRole(int $roleId) : int
	{
		return (int) ($this->_data[$roleId]->total ?? 0);
	}

	/**
	 * Retrieve the average amount of registered users for the given role per year
	 * @param $roleId int A role ID constant
	 * @return float
	 */
	public function getRegistrationsByRolePerYear(int $roleId) : float
	{
		return (float) ($this->_data[$roleId]->average ?? 0);
	}

	/**
	 * Retrieve the amount of registered users
	 * @return int
	 */
	public function getRegistrations() : int
	{
		return self::getRegistrationsByRole(0);
	}

	/**
	 * Retrieve the average amount of registered users per year
	 * @return float
	 */
	public function getRegistrationsPerYear() : float
	{
		return self::getRegistrationsByRolePerYear(0);
	}

	/**
	 * Retrieve a list of the roles which received registrations in the period
	 * @return iterable
	 */
	public function getRegisteredRoles() : iterable
	{
		return array_keys($this->_data);
	}
}
