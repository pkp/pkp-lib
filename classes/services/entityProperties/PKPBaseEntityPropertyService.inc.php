<?php

/**
 * @file classes/services/PKPBaseEntityPropertyService.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPBaseEntityPropertyService
 * @ingroup services_entity_properties
 *
 * @brief This is a base class which implements EntityPropertyInterface.
 */

namespace PKP\Services\EntityProperties;

use \DBResultRange;
use \PKP\Services\Exceptions\InvalidServiceException;

// The type of action against which data should be validated. When adding an
// entity, required properties must be present and not empty.
define('VALIDATE_ACTION_ADD', 'add');
define('VALIDATE_ACTION_EDIT', 'edit');

abstract class PKPBaseEntityPropertyService implements EntityPropertyInterface {

	/** @var object $service */
	protected $service = null;

	/**
	 * Constructor
	 * @param object $service
	 * @throws PKP\Services\Exceptions\InvalidServiceException
	 */
	public function __construct($service) {
		$serviceNamespace = (new \ReflectionObject($service))->getNamespaceName();
		if (!in_array($serviceNamespace, array('PKP\Services', 'OJS\Services', 'OMP\Services'))) {
			throw new InvalidServiceException();
		}

		$this->service = $service;
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityPropertyInterface::getProperties()
	 */
	abstract public function getProperties($entity, $props, $args = null);

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityPropertyInterface::getSummaryProperties()
	 */
	abstract public function getSummaryProperties($entity, $args = null);

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityPropertyInterface::getFullProperties()
	 */
	abstract public function getFullProperties($entity, $args = null);

	/**
	 * A helper function to retrieve the DBResultRange for a query from the
	 * request arguments.
	 *
	 * @param $args array
	 * @return string
	 */
	protected function getRangeByArgs($args) {
		$range = null;
		if (isset($args['count'])) {
			$range = new DBResultRange($args['count'], null, isset($args['offset'])?$args['offset']:0);
		}
		return $range;
	}
}
