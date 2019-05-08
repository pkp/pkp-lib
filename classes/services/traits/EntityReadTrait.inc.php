<?php
/**
 * @file classes/services/interfaces/EntityReadTrait.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EntityReadTrait
 * @ingroup services_interfaces
 *
 * @brief Traits for service classes that implement EntityReadInterface
 */
namespace PKP\Services\Traits;

trait EntityReadTrait {
	/**
	 * Retrieve the DBResultRange for a query from the request arguments.
	 *
	 * @param $args array
	 *   $args['count'] int Number of items to get
	 *   $args['offset'] int Get items after this number. 0 if not set
	 * @return DBResultRange|null
	 */
	protected function getRangeByArgs($args) {
		$range = null;
		if (isset($args['count'])) {
			import('lib.pkp.classes.db.DBResultRange');
			$range = new \DBResultRange($args['count'], null, isset($args['offset'])?$args['offset']:0);
		}
		return $range;
	}
}
