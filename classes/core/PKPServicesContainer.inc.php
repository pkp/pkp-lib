<?php

/**
 * @file classes/core/ServicesContainer.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ServicesContainer
 * @ingroup core
 * @see Core
 *
 * @brief Pimple Dependency Injection Container.
 */

require_once(dirname(__FILE__) . '/../../lib/vendor/autoload.php');

abstract class PKPServicesContainer {

	/** @var Pimple\Container Pimple Dependency Injection Container */
	private static $instance = null;

	protected $container = null;

	/**
	 * private constructor
	 */
	private function __construct() {
		require_once(dirname(__FILE__) . '/../../lib/vendor/pimple/pimple/src/Pimple/Container.php');
		$this->container = new Pimple\Container();
		$this->init();
	}

	/**
	 * container initialization
	 */
	abstract protected function init();

	/**
	 * Get service from container
	 * @param string $service
	 */
	public function get($service) {
		return $this->container[$service];
	}

	/**
	 * Returns the instance of the container
	 */
	public static function instance() {
		if (is_null(self::$instance)) {
			self::$instance = new ServicesContainer();
		}

		return self::$instance;
	}

}
