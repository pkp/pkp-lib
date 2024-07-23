<?php
/**
 * @file tests/phpunit-bootstrap.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @ingroup tests
 *
 * @brief PKP-specific phpunit bootstrap file
 *
 * @see tools/runAllTests.sh
 */

use PKP\core\PKPSessionGuard;

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

// Recommended settings for PHPUnit
ini_set('memory_limit', '-1');
ini_set('error_reporting', '-1');
ini_set('log_errors_max_len', '0');
ini_set('assert.exception', '1');
ini_set('xdebug.show_exception_trace', '0');

// Show errors in the UI
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

// Set up minimal PKP application environment
require_once 'lib/pkp/includes/bootstrap.php';

// Disable the session initialization
PKPSessionGuard::disableSession();
