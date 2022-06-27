<?php

/**
 * @file tests/DatabaseTestCase.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DatabaseTestCase
 * @ingroup tests
 *
 * @brief Base class for unit tests that require database support.
 *        The schema TestName.setUp.xml will be installed before each
 *        individual test case (if present). The schema TestName.tearDown.xml may
 *        be used to clean up after each test case.
 */


import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.tests.PKPTestHelper');

abstract class DatabaseTestCase extends PKPTestCase
{
    /**
     * Override this method if you want to backup/restore
     * tables before/after the test.
     *
     * @return array A list of tables to backup and restore.
     */
    protected function getAffectedTables()
    {
        return [];
    }

    /**
     * @copydoc PHPUnit_Framework_TestCase::setUp()
     */
    protected function setUp(): void
    {
        parent::setUp();
        // Switch off xdebug screaming (there are
        // errors in adodb...).
        PKPTestHelper::xdebugScream(false);

        // Backup affected tables.
        $affectedTables = $this->getAffectedTables();
        if (is_array($affectedTables)) {
            PKPTestHelper::backupTables($affectedTables, $this);
        }
    }

    /**
     * @copydoc PHPUnit_Framework_TestCase::tearDown()
     */
    protected function tearDown(): void
    {
        $affectedTables = $this->getAffectedTables();
        if (is_array($affectedTables)) {
            PKPTestHelper::restoreTables($this->getAffectedTables(), $this);
        } elseif ($affectedTables === PKP_TEST_ENTIRE_DB) {
            PKPTestHelper::restoreDB($this);
        }

        // Switch xdebug screaming back on.
        PKPTestHelper::xdebugScream(true);
        parent::tearDown();
    }
}
