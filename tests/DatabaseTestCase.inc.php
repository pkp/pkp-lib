<?php

/**
 * @file tests/DatabaseTestCase.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class DatabaseTestCase
 * @ingroup tests
 *
 * @brief Base class for unit tests that require database support.
 *        The schema TestName.setUp.xml will be installed before each
 *        individual test case (if present). The schema TestName.tearDown.xml may
 *        be used to clean up after each test case.
 */

// $Id$

import('lib.pkp.tests.PKPTestCase');

abstract class DatabaseTestCase extends PKPTestCase {
	const
		// Available test configurations
		CONFIG_PGSQL = 'pgsql',
		CONFIG_MYSQL = 'mysql',

		// Test phases
		TEST_SET_UP = 1,
		TEST_TEAR_DOWN = 2;

	private
		$_testSchemaFile = null,
		$_testSchema = null;


	protected function setUp() {
		// By default we use the MySQL test configuration
		$this->setTestConfiguration(self::CONFIG_MYSQL);

		// Database setup
		$this->installTestSchema(self::TEST_SET_UP);
	}

	protected function tearDown() {
		// Clean up database
		$this->installTestSchema(self::TEST_TEAR_DOWN);
	}

	/**
	 * (Un-)install the test schema (if it exists)
	 * @param $testPhase string
	 */
	private function installTestSchema($testPhase) {
		if (is_readable($this->getTestSchemaFile($testPhase))) {
			if (is_null($this->_testSchema)) {
				import('lib.pkp.classes.db.compat.AdodbXmlschemaCompat');
				$this->_testSchema = &new AdodbXmlschemaCompat(
					DBConnection::getConn(),
					Config::getVar('i18n', 'database_charset')
				);
			}
			$this->_testSchema->ParseSchema($this->getTestSchemaFile($testPhase));
			$this->_testSchema->ExecuteSchema();
		}
	}

	/**
	 * Identify the canonical file name of
	 * the test-specific schema.
	 * @param $testPhase string
	 * @return string
	 */
	private function getTestSchemaFile($testPhase) {
		if (is_null($this->_testSchemaFile)) {
			$testName = get_class($this);
			$loadedFiles = get_included_files();
			foreach ($loadedFiles as $loadedFile) {
				if (strpos($loadedFile, $testName) !== FALSE) {
					$testFile = $loadedFile;
					break;
				}
			}
			$this->_testSchemaFile = substr($testFile, 0, -4);
		}

		switch($testPhase) {
			case self::TEST_SET_UP:
				return $this->_testSchemaFile . '.setUp.xml';
				break;

			case self::TEST_TEAR_DOWN:
				return $this->_testSchemaFile . '.tearDown.xml';
				break;

			default:
				fatalError('OJS Test Case: Unknown test phase');
		}
	}
}
?>
