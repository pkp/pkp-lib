<?php
/**
 * @file tests/PKPTestHelper.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class TestHelper
 * @ingroup tests
 *
 * @brief Class that implements functionality common to all PKP test types.
 */

abstract class PKPTestHelper {

	//
	// Public helper methods
	//
	/**
	 * Backup the given tables.
	 * @param $tables array
	 * @param $test PHPUnit_Framework_Assert
	 */
	public static function backupTables($tables, $test) {
		$dao = new DAO();
		foreach ($tables as $table) {
			$sqls = array(
				"ALTER TABLE $table RENAME TO backup_$table",
				"CREATE TABLE $table LIKE backup_$table",
				"INSERT INTO $table SELECT * FROM backup_$table"
			);
			foreach ($sqls as $sql) {
				if (!$dao->update($sql, false, true, false)) {
					self::restoreTables($tables, $test);
					$test->fail("Error while backing up $table: offending SQL is '$sql'");
				}
			}
		}
	}

	/**
	 * Restore the given tables.
	 * @param $tables array
	 * @param $test PHPUnit_Framework_Assert
	 */
	public static function restoreTables($tables, $test) {
		$dao = new DAO();
		foreach ($tables as $table) {
			$sqls = array(
				"ALTER TABLE $table RENAME TO temp_$table",
				"ALTER TABLE backup_$table RENAME TO $table",
				"DROP TABLE temp_$table" // Only drop original table if we're sure that we really had a backup!
			);
			foreach ($sqls as $sql) {
				if (!$dao->update($sql, false, true, false)) {
					// Try to reset to the prior state before giving up.;
					$dao->update("ALTER TABLE temp_$table RENAME TO $table");
					$test->fail("Error while restoring $table: offending SQL is '$sql'");
				}
			}
		}
	}

	/**
	 * Some 3rd-party libraries (i.e. adodb)
	 * use the PHP @ operator a lot which can lead
	 * to test failures when xdebug's scream parameter
	 * is on. This helper method can be used to safely
	 * (de)activate this.
	 *
	 * If the xdebug extension is not installed then
	 * this method does nothing.
	 *
	 * @param $scream boolean
	 */
	public static function xdebugScream($scream) {
		if (extension_loaded('xdebug')) {
			static $previous = null;
			if ($scream) {
				assert(!is_null($previous));
				ini_set('xdebug.scream', $previous);
			} else {
				$previous = ini_get('xdebug.scream');
				ini_set('xdebug.scream', false);
			}
		}
	}
}
?>
