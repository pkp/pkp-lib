<?php
/**
 * PKP-specific phpunit bootstrap file.
 *
 * Integrates PHPUnit with the PKP application environment
 * and enables running/debugging tests from within Eclipse or
 * other CASE tools.
 */

use PKP\cache\CacheManager;
use PKP\session\SessionManager;

// This script may not be executed remotely.
if (isset($_SERVER['SERVER_NAME'])) {
    exit('This script can only be executed from the command-line.');
}

// Configure the index file location, assume that pkp-lib is included within a PKP application.
define('INDEX_FILE_LOCATION', dirname(__FILE__, 4) . '/index.php');
chdir(dirname(INDEX_FILE_LOCATION));

// Configure PKP error handling for tests
define('DONT_DIE_ON_ERROR', true);

// Configure assertions for tests
ini_set('assert.active', '1');
ini_set('assert.bail', '0');
ini_set('assert.warning', '1');
ini_set('assert.callback', '');
ini_set('assert.quiet_eval', '0');

// NB: Our test framework provides the possibility to
// import mock classes to replace regular classes.
// This is necessary to mock static method calls.
// Unfortunately we can only define one mock environment
// per test run as PHP does not allow to change a class
// implementation while running.
// We therefore need to define the mock environment globally
// so that tests can check their environment requirement
// before they start importing.
if (isset($_SERVER['PKP_MOCK_ENV'])) {
    define('PHPUNIT_CURRENT_MOCK_ENV', $_SERVER['PKP_MOCK_ENV']);
    $mockEnvs = '';
    foreach (['lib/pkp/tests/mock/', 'tests/mock/'] as $testDir) {
        $normalizedMockEnv = normalizeMockEnvironment($testDir . $_SERVER['PKP_MOCK_ENV']);
        if ($normalizedMockEnv) {
            if (!empty($mockEnvs)) {
                $mockEnvs .= ';';
            }
            $mockEnvs .= $normalizedMockEnv;
        }
    }
    define('PHPUNIT_ADDITIONAL_INCLUDE_DIRS', $mockEnvs);
} else {
    // Use the current test folder as mock environment
    // if no environment has been explicitly set.
    // The phpunit cli tool's last parameter is the test class, file or directory
    define('PHPUNIT_CURRENT_MOCK_ENV', '__NONE__');
    assert(is_array($_SERVER['argv']) and count($_SERVER['argv']) > 1);
    $testDir = end($_SERVER['argv']);
    define('PHPUNIT_ADDITIONAL_INCLUDE_DIRS', normalizeMockEnvironment($testDir));
}

/**
 *  A function to declare dependency on a mock environment.
 *  Tests depending on static mock classes should use this
 *  function so that they cannot be executed in the wrong
 *  test environment.
 *
 *  @param string $mockEnv
 */
function require_mock_env($mockEnv)
{
    if (PHPUNIT_CURRENT_MOCK_ENV == '__NONE__' || PHPUNIT_CURRENT_MOCK_ENV != $mockEnv) {
        // Tests that require different mock environments cannot run
        // in the same test batch as this would require re-defining
        // already defined classes.
        debug_print_backtrace();
        exit(
            'You are trying to run a test in the wrong mock environment ('
            . PHPUNIT_CURRENT_MOCK_ENV . ' rather than ' . $mockEnv . ')!'
        );
    }
}

/**
 * Provide a test-specific implementation of the import function
 * so we can drop in mock classes, especially to mock
 * static method calls.
 *
 * @deprecated 3.4.0
 * @see bootstrap.php
 *
 * @param string $class
 */
function import($class)
{
    static $mockEnvArray = null;

    // Expand and verify additional include directories.
    if (is_null($mockEnvArray)) {
        if (defined('PHPUNIT_ADDITIONAL_INCLUDE_DIRS')) {
            $mockEnvArray = explode(';', PHPUNIT_ADDITIONAL_INCLUDE_DIRS);
            foreach ($mockEnvArray as $mockEnv) {
                if (!is_dir($mockEnv)) {
                    exit('Invalid mock environment directory ' . $mockEnv . '!');
                }
            }
        } else {
            $mockEnvArray = [];
        }
    }

    // Test whether we have a mock implementation of
    // the requested class.
    foreach ($mockEnvArray as $mockEnv) {
        $classParts = explode('.', $class);
        $mockClassFile = $mockEnv . '/Mock' . array_pop($classParts) . '.php';
        if (file_exists($mockClassFile)) {
            require_once $mockClassFile;
            return;
        }
    }

    // No mock implementation found, do the normal import
    if (file_exists($filename = './' . str_replace('.', '/', $class) . '.php')) {
        require_once($filename);
    } else {
        require_once('./' . str_replace('.', '/', $class) . '.inc.php');
    }
}

/**
 * A function to transform a mock environment name
 * in a list of additional include directories.
 *
 * @param string $mockEnv
 *
 * @return string A mock environment directory to check when
 * importing class files.
 */
function normalizeMockEnvironment($mockEnv)
{
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
require_once('./lib/pkp/includes/bootstrap.php');

// Make sure ADOdb doesn't "clean up" our /tmp folder.
$ADODB_CACHE_DIR = CacheManager::getFileCachePath() . '/_db';

// Disable the session initialization
SessionManager::disable();

// Remove the PKP error handler so that PHPUnit
// can set its own error handler and catch errors for us.
error_reporting(E_ALL & ~E_STRICT & ~E_DEPRECATED);

// Show errors in the UI
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

spl_autoload_register(function ($class) {
    $prefix = 'PKP\\tests';
    $rootPath = BASE_SYS_DIR . '/lib/pkp/tests';
    customAutoload($rootPath, $prefix, $class);
});

spl_autoload_register(function ($class) {
    $prefix = 'APP\\tests';
    $rootPath = BASE_SYS_DIR . '/tests';
    customAutoload($rootPath, $prefix, $class);
});
