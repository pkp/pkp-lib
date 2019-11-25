<?php

/**
 * @defgroup index Index
 * Bootstrap and initialization code.
 */

/**
 * @file includes/bootstrap.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
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

define('ENV_SEPARATOR', strtolower(substr(PHP_OS, 0, 3)) == 'win' ? ';' : ':');
if (!defined('DIRECTORY_SEPARATOR')) {
	// Older versions of PHP do not define this
	define('DIRECTORY_SEPARATOR', strtolower(substr(PHP_OS, 0, 3)) == 'win' ? '\\' : '/');
}
define('BASE_SYS_DIR', dirname(INDEX_FILE_LOCATION));
chdir(BASE_SYS_DIR);

// Update include path - for backwards compatibility only
// Try to use absolute (/...) or relative (./...) filenames
// wherever possible to bypass the costly file name normalization
// process.
ini_set('include_path', '.'
	. ENV_SEPARATOR . BASE_SYS_DIR . '/classes'
	. ENV_SEPARATOR . BASE_SYS_DIR . '/pages'
	. ENV_SEPARATOR . BASE_SYS_DIR . '/lib/pkp'
	. ENV_SEPARATOR . BASE_SYS_DIR . '/lib/pkp/classes'
	. ENV_SEPARATOR . BASE_SYS_DIR . '/lib/pkp/pages'
	. ENV_SEPARATOR . BASE_SYS_DIR . '/lib/pkp/lib/vendor/adodb/adodb-php'
	. ENV_SEPARATOR . ini_get('include_path')
);

if (key_exists('HTTP_ACCEPT', $GLOBALS['_SERVER']) &&
	$GLOBALS['_SERVER']['HTTP_ACCEPT'] == 'application/json') {
	register_shutdown_function(function() {
		$error = error_get_last();
		if (in_array($error['type'], [E_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
			http_response_code(500);
			header('content-type: application/json');
			echo(json_encode([
				'error' => 'api.500.serverError',
				'errorMessage' => "The request cannot be completed due to a server error.",
			]));
			error_log("ERROR: " . $error['file'] . ": " . $error['line'] . ": " . $error['message']);
		}
	});

	set_exception_handler(function ($exception) {
		http_response_code(500);
		header('content-type: application/json');
		echo(json_encode([
			'error' => 'api.500.serverError',
			'errorMessage' => "The request cannot be completed due to a server error.",
		]));
		error_log("EXCEPTION: " . $exception->getFile() . ": " . $exception->getLine() . ": " . $exception->getMessage());
		die();
	});
}

// System-wide functions
require('./lib/pkp/includes/functions.inc.php');

// Initialize the application environment
import('classes.core.Application');

return new Application();
