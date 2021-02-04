<?php
/**
 * @file classes/services/interfaces/EntityReadInterface.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EntityReadInterface
 * @ingroup services_interfaces
 *
 * @brief An interface describing the methods a service class will implement to
 *  get one object or a collection of objects.
 */
namespace PKP\Services\Interfaces;

interface EntityReadInterface {
	/**
	 * Get one object of the entity type by its ID
	 *
	 * @param int $id
	 * @return object
	 */
	public function get($id);

	/**
	 * Get a count of the number of objects matching $args
	 *
	 * @param array $args Assoc array describing which rows should be counted
	 * @return int
	 */
	public function getCount($args = []);

	/**
	 * Get a list of ids matching $args
	 *
	 * @param array $args Assoc array describing which ids should be retrieved
	 * @return array
	 */
	public function getIds($args = []);

	/**
	 * Get a collection of objects limited, filtered and sorted by $args
	 *
	 * @param array $args Assoc array describing which objects should be retrieved
	 * @return \Iterator
	 */
	public function getMany($args = []);

	/**
	 * Get the max count of objects matching $args
	 *
	 * This method is identical to `self::getCount()` except that any pagination
	 * arguments such as `count` or `offset` will be ignored.
	 *
	 * Usually, this is used with `self::getMany()` to return the total number of
	 * items available according to the selection criteria.
	 *
	 * @param array $args Assoc array describing which objects should be counted
	 * @return int
	 */
	public function getMax($args = []);

	/**
	 * Get a QueryBuilder for this entity configured
	 * according to the $args passed
	 *
	 * @param array $args Assoc array describing how the querybuilder should be
	 * configured.
	 * @return Object
	 */
	public function getQueryBuilder($args = []);
}
