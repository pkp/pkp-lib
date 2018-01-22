<?php

/**
 * @file classes/core/PKPServicesContainer.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPServicesContainer
 * @ingroup core
 * @see Core
 *
 * @brief Pimple Dependency Injection Container.
 */


abstract class PKPServicesContainer {

	/** @var Pimple\Container Pimple Dependency Injection Container */
	private static $instance = null;

	protected $container = null;

	/**
	 * private constructor
	 */
	private function __construct() {
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
