<?php

/**
 * @file classes/services/queryBuilders/BaseQueryBuilder.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class BaseQueryBuilder
 * @ingroup services_query_builders
 *
 * @brief Query builder base class
 */

namespace PKP\Services\QueryBuilders;

use Illuminate\Database\Capsule\Manager as Capsule;
use \Config;

abstract class BaseQueryBuilder {

	/** @var object capsule  */
	protected $capsule = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->bootstrap();
	}

	/**
	 * bootstrap query builder
	 */
	protected function bootstrap() {

		// Map valid OJS3 config options to Illuminate database drivers
		$driver = strtolower(Config::getVar('database', 'driver'));
		if (substr($driver, 0, 8) === 'postgres') {
			$driver = 'pgsql';
		} else {
			$driver = 'mysql';
		}

		// Always use `utf8` unless `latin1` is specified
		$charset = Config::getVar('i18n', 'connection_charset');
		if ($charset !== 'latin1') {
			$charset = 'utf8';
		}

		$capsule = new Capsule;
		$capsule->addConnection(array(
				'driver'    => $driver,
				'host'      => Config::getVar('database', 'host'),
				'database'  => Config::getVar('database', 'name'),
				'username'  => Config::getVar('database', 'username'),
			        'port'      => Config::getVar('database', 'port'),
			        'unix_socket'=> Config::getVar('database', 'unix_socket'),
				'password'  => Config::getVar('database', 'password'),
				'charset'   => $charset,
				'collation' => 'utf8_general_ci',
		));
		$capsule->setAsGlobal();
	}
}
