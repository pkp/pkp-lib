<?php
/**
 * PKP-specific phpunit bootstrap file.
 *
 * Integrates PHPUnit with the PKP application environment
 * and enables running/debugging tests from within Eclipse or
 * other CASE tools.
 */


// Configure the index file location, assume that pkp-lib is
// included within a PKP application.
// FIXME: This doesn't work if lib/pkp is symlinked.
// realpath($_['SCRIPT_FILENAME'].'/../../index.php') could work
// but see http://bugs.php.net/bug.php?id=50366
define('INDEX_FILE_LOCATION', dirname(dirname(dirname(dirname(__FILE__)))).'/index.php');
chdir(dirname(INDEX_FILE_LOCATION));

// Configure PKP error handling for tests
define('DONT_DIE_ON_ERROR', true);

// Don't support sessions
define('SESSION_DISABLE_INIT', true);

// Configure assertions for tests
ini_set('assert.active', true);
ini_set('assert.bail', false);
ini_set('assert.warning', true);
ini_set('assert.callback', null);
ini_set('assert.quiet_eval', false);

// Log errors to test specific error log
ini_set('error_log', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'results' . DIRECTORY_SEPARATOR . 'error.log');

// A global variable that contains a directory with
// a mock class environment for a whole test suite.
// We need to define this as a global variable so that
// tests can define check their environment requirement
// before they start importing.
if (isset($_SERVER['PKP_MOCK_ENV'])) {
	define('CURRENT_MOCK_ENV', normalizeMockEnvironment($_SERVER['PKP_MOCK_ENV']));
} else {
	// Use the current test folder as mock environment
	// if no environment has been explicitly set.
	// The phpunit cli tool's last parameter is the test class, file or directory
	assert(is_array($_SERVER['argv']) and count($_SERVER['argv'])>1);
	$testDir = end($_SERVER['argv']);
	define('CURRENT_MOCK_ENV', normalizeMockEnvironment($testDir));
}

// A function to declare dependency on a mock environment.
function require_mock_env($mockEnv) {
	$mockEnv = normalizeMockEnvironment($mockEnv);
	if (CURRENT_MOCK_ENV != $mockEnv) {
		// Tests that require different mock environments cannot run
		// in the same test batch as this would require re-defining
		// already defined classes.
		debug_print_backtrace();
		die(
			'You are trying to run a test in the wrong mock environment ('
			.CURRENT_MOCK_ENV.' rather than '.$mockEnv.')!'
		);
	}
}

// Provide a test-specific implementation of the import function
// so we can drop in mock classes, especially to mock
// static method calls.
function import($class) {
	// Test whether we have a mock implementation of
	// the requested class.
	if (CURRENT_MOCK_ENV && is_dir(CURRENT_MOCK_ENV)) {
		$classParts = explode('.', $class);
		$mockClassFile = CURRENT_MOCK_ENV.'/Mock'.array_pop($classParts).'.inc.php';
		if (file_exists($mockClassFile)) {
			require_once($mockClassFile);
			return;
		}
	}

	// No mock implementation found, do the normal import
	require_once('./'.str_replace('.', '/', $class) . '.inc.php');
}

function normalizeMockEnvironment($mockEnv) {
		if (substr($mockEnv, 0, 1) != '/') {
			$mockEnv = getcwd() . '/' . $mockEnv;
		}
		if (!is_dir($mockEnv)) {
			$mockEnv = dirname($mockEnv);
		}
		$mockEnv = realpath($mockEnv);

		// Test whether this is a valid directory.
		if (is_dir($mockEnv)) {
			return $mockEnv;
		} else {
			// Make sure that we do not try to
			// identify a mock env again but mark
			// it as "not found".
			return false;
		}
}

// Set up minimal PKP application environment
require_once('./lib/pkp/includes/bootstrap.inc.php');

// Remove the PKP error handler so that PHPUnit
// can set it's own error handler and catch errors for us.
restore_error_handler();

// Show errors in the UI
ini_set('display_errors', true);
