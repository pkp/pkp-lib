<?php
/**
 * @file classes/services/interfaces/EntityReadInterface.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
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
	 * @param $id integer
	 * @return object
	 */
	public function get($id);

	/**
	 * Get a collection of objects limited, filtered and sorted by $args
	 *
	 * @param $args array Assoc array describing which objects should be retrieved
	 * @return Iterator
	 */
	public function getMany($args = null);

	/**
	 * Get the max count of objects found matching $args
	 *
	 * Usually, $args are identical to those passed to self::getMany(), and return
	 * the total number of items available according to that criteria.
	 *
	 * @param $args array Assoc array describing which objects should be counted
	 * @return int
	 */
	public function getMax($args = null);
}
