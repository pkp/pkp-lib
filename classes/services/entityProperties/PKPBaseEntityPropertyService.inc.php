<?php

/**
 * @file classes/services/PKPBaseEntityPropertyService.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPBaseEntityPropertyService
 * @ingroup services_entity_properties
 *
 * @brief This is a base class which implements EntityPropertyInterface.
 */

namespace PKP\Services\EntityProperties;

use \PKP\Services\Exceptions\InvalidServiceException;

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
}
