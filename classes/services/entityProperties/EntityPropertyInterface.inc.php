<?php

/**
 * @file classes/services/entityProperties/EntityPropertyInterface.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EntityPropertyInterface
 * @ingroup services_entity_properties
 *
 * @brief Entity properties interface definition.
 */

namespace PKP\Services\EntityProperties;

interface EntityPropertyInterface {
	/**
	 * Returns values given a list of properties of en entity
	 * @param $entity object
	 * @param $props array
	 * @param $args array extra arguments
	 * @return array
	 */
	public function getProperties($entity, $props, $args = null);

	/**
	 * Returns summary properties for an entity
	 * @param $entity object
	 * @param $args array extra arguments
	 *		$args['request'] PKPRequest Required
	 *		$args['slimRequest'] SlimRequest
	 * @return array
	 */
	public function getSummaryProperties($entity, $args = null);

	/**
	 * Returns full properties for an entity
	 * @param $entity object
	 * @param $args array extra arguments
	 *		$args['request'] PKPRequest Required
	 *		$args['slimRequest'] SlimRequest
	 * @return array
	 */
	public function getFullProperties($entity, $args = null);
}
