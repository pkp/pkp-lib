<?php

/**
 * @file tests/classes/db/DBConnectionTest.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class DBConnectionTest
 * @ingroup tests_classes_db
 * @see DBConnection
 *
 * @brief Tests for the DBConnectionTest class.
 */

import('lib.pkp.tests.DatabaseTestCase');
import('lib.pkp.classes.db.DBConnection');

class DBConnectionTest extends DatabaseTestCase {
	/**
	 * @covers DBConnection::DBConnection
	 * @covers DBConnection::initDefaultDBConnection
	 * @covers DBConnection::initConn
	 * @covers AdodbMysqlCompat::AdodbMysqlCompat
	 */
	public function testInitDefaultDBConnection() {
		$conn = new DBConnection();
		$dbConn = $conn->getDBConn();
		self::assertType('ADODB_mysql', $dbConn);
		$conn->disconnect();
		unset($conn);
	}

	/**
	 * @covers DBConnection::DBConnection
	 * @covers DBConnection::initDefaultDBConnection
	 * @covers DBConnection::initConn
	 * @covers AdodbPostgres7Compat::AdodbPostgres7Compat
	 */
	public function testInitPostgresDBConnection() {
		$this->setTestConfiguration(self::CONFIG_PGSQL);
		$conn = new DBConnection();
		$dbConn = $conn->getDBConn();
		self::assertType('ADODB_postgres7', $dbConn);
		$conn->disconnect();
		unset($conn);
	}

	/**
	 * @covers DBConnection::DBConnection
	 * @covers DBConnection::initCustomDBConnection
	 * @covers DBConnection::initConn
	 */
	public function testInitCustomDBConnection() {
		$this->setTestConfiguration(self::CONFIG_PGSQL);
		$conn = new DBConnection('sqlite', 'localhost', 'ojs', 'ojs', 'ojs', true, false, false);
		$dbConn = $conn->getDBConn();
		self::assertType('ADODB_sqlite', $dbConn);
		$conn->disconnect();
		unset($conn);
	}
}

?>
