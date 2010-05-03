<?php
/**
 * A wrapper around $PHPRC/phpunit
 *
 * It integrates PHPUnit with the PKP application environment
 * and enables running/debugging tests from within Eclipse or
 * other CASE tools.
 */
// Configure the index file location, assume that pkp-lib is
// included within a PKP application.
// FIXME: This doesn't work if lib/pkp is symlinked. realpath($_['SCRIPT_FILENAME'].'/../../index.php') could work but see http://bugs.php.net/bug.php?id=50366
define('INDEX_FILE_LOCATION', dirname(dirname(dirname(dirname(__FILE__)))).'/index.php');
chdir(dirname(INDEX_FILE_LOCATION));

// Configure PKP error handling for tests
define('DONT_DIE_ON_ERROR', true);

// Don't support sessions
define('SESSION_DISABLE_INIT', true);

// Log errors to test specific error log
ini_set('error_log', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'results' . DIRECTORY_SEPARATOR . 'error.log');

// The phpunit cli tool's last parameter is the test class, file or directory
if (is_array($argv) and count($argv)>1) {
	$testDir = dirname(end($argv));

	// test whether this is a valid directory
	if (is_dir($testDir)) {
		define('TEST_BASE_DIRECTORY', $testDir);
	} else {
		define('TEST_BASE_DIRECTORY', false);
	}
}

if (TEST_BASE_DIRECTORY) {
	// Provide our own implementation of the import function
	// so we can drop in mock classes, especially to mock
	// static method calls.
	function import($class) {
		// Test whether we have a mock implementation of
		// the requested class.
		$classParts = explode('.', $class);
		$mockClassFile = TEST_BASE_DIRECTORY.'/Mock'.array_pop($classParts).'.inc.php';
		if (file_exists($mockClassFile)) {
			require_once($mockClassFile);
		} else {
			// No mock implementation found, do the normal import
			require_once('./'.str_replace('.', '/', $class) . '.inc.php');
		}
	}
}

// Set up minimal PKP application environment
require_once('./lib/pkp/includes/bootstrap.inc.php');

// Remove the PKP error handler so that PHPUnit
// can set it's own error handler and catch errors for us.
restore_error_handler();

// Show errors in the UI
ini_set('display_errors', true);

// Configure assertions for tests
ini_set('assert.active', true);
ini_set('assert.bail', false);
ini_set('assert.warning', true);
ini_set('assert.callback', null);
ini_set('assert.quiet_eval', false);

// The server variable PHP_PEAR_BIN_DIR points to the PEAR base directory
include $_SERVER['PHP_PEAR_BIN_DIR'] . '/phpunit';

?>
