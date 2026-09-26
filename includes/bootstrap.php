<?php

/**
 * @defgroup index Index
 * Bootstrap and initialization code.
 */

/**
 * @file includes/bootstrap.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @ingroup index
 *
 * @brief Core system initialization code.
 * This file is loaded before any others.
 * Any system-wide imports or initialization code should be placed here.
 */


/**
 * Basic initialization (pre-classloading).
 */

// Load Composer autoloader
require_once 'lib/pkp/lib/vendor/autoload.php';

define('BASE_SYS_DIR', dirname(INDEX_FILE_LOCATION));
chdir(BASE_SYS_DIR);

// System-wide functions
require_once './lib/pkp/includes/functions.php';

// Test-mode config redirection: APPLICATION_ENV=test → config.test.inc.php.
// tools/installTest.php is responsible for ensuring the file exists
// (seeded from config.TEMPLATE.inc.php) before install runs.
if (getenv('APPLICATION_ENV') === 'test') {
	\PKP\config\Config::setConfigFileName(
		\PKP\core\Core::getBaseDir() . '/config.test.inc.php'
	);
}

// Initialize the application environment
return new \APP\core\Application();
