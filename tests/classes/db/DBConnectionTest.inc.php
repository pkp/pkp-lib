<?php

/**
 * @file tests/db/DBConnectionTest.inc.php
 *
 * Copyright (c) 2003-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class DBConnectionTest
 * @ingroup tests
 * @see DBConnection
 *
 * @brief Tests for the DBConnectionTest class.
 */

// $Id: DBConnectionTest.inc.php,v 1.1 2009/10/27 21:58:08 jerico.dev Exp $

import('tests.DatabaseTestCase');
import('classes.db.DBConnection');

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
