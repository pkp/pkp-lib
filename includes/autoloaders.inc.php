<?php 

/**
 * @file includes/autoloaders.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @ingroup index
 *
 * @brief Registers custom autoloaders 
 */

// registering autoloader function for OJS namespace
spl_autoload_register(function($class) {
	$prefix = 'OJS\\';

	if (substr($class, 0, strlen($prefix)) !== $prefix) {
		return;
	}

	$class = substr($class, strlen($prefix));
	$parts = explode('\\', $class);
	
	// we expect at least one folder in the namespace 
	// there is no class defined directly under classes/ folder
	if (count($parts) < 2) {
		return;
	}
	
	$className = Core::cleanFileVar(array_pop($parts));
	$parts = array_map(function($part) {
		return strtolower(Core::cleanFileVar($part));
	}, $parts);

	$subParts = join('/', $parts);
	$filePath = BASE_SYS_DIR . "/classes/{$subParts}/{$className}.inc.php";

	if (is_file($filePath)) {
		require_once($filePath);
	}
});

// registering autoloader function for PKP namespace
spl_autoload_register(function($class) {
	$prefix = 'PKP\\';

	if (substr($class, 0, strlen($prefix)) !== $prefix) {
		return;
	}
	
	$class = substr($class, strlen($prefix));
	$parts = explode('\\', $class);
	
	// we expect at least one folder in the namespace 
	// there is no class defined directly under classes/ folder
	if (count($parts) < 2) {
		return;
	}
	
	$className = Core::cleanFileVar(array_pop($parts));
	$parts = array_map(function($part) {
		return strtolower(Core::cleanFileVar($part));
	}, $parts);

	$subParts = join('/', $parts);
	$filePath = BASE_SYS_DIR . "/lib/pkp/classes/{$subParts}/{$className}.inc.php";

	if (is_file($filePath)) {
		require_once($filePath);
	}
});
