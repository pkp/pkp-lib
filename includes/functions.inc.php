<?php

/**
 * @file functions.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @ingroup index
 *
 * @brief Contains definitions for common functions used system-wide.
 * Any frequently-used functions that cannot be put into an appropriate class should be added here.
 */

//$Id$


/**
 * Emulate a Java-style import statement.
 * Simply includes the associated PHP file (using require_once so multiple calls to include the same file have no effect).
 * @param $class string the complete name of the class to be imported (e.g. "core.Core")
 */
if (!function_exists('import')) {
	function import($class) {
		require_once(str_replace('.', '/', $class) . '.inc.php');
	}
}

if (!function_exists('file_get_contents')) {
	// For PHP < 4.3.0
	function file_get_contents($file) {
		return join('', file($file));
	}
}

/**
 * Wrapper around die() to pretty-print an error message with an optional stack trace.
 */
function fatalError($reason) {
	// Because this method may be called when checking the value of the show_stacktrace
	// configuration string, we need to ensure that we don't get stuck in an infinite loop.
	static $isErrorCondition = null;
	static $showStackTrace = false;

	if ($isErrorCondition === null) {
		$isErrorCondition = true;
		$showStackTrace = Config::getVar('debug', 'show_stacktrace');
		$isErrorCondition = false;
	}

	echo "<h1>$reason</h1>";

	if ($showStackTrace && checkPhpVersion('4.3.0')) {
		echo "<h4>Stack Trace:</h4>\n";
		$trace = debug_backtrace();

		// Remove the call to fatalError from the call trace.
		array_shift($trace);

		// Back-trace pretty-printer adapted from the following URL:
		// http://ca3.php.net/manual/en/function.debug-backtrace.php
		// Thanks to diz at ysagoon dot com

		// FIXME: Is there any way to localize this when the localization
		// functions may have caused the failure in the first place?
		foreach ($trace as $bt) {
			$args = '';
			if (isset($bt['args'])) foreach ($bt['args'] as $a) {
				if (!empty($args)) {
					$args .= ', ';
				}
				switch (gettype($a)) {
					case 'integer':
					case 'double':
						$args .= $a;
						break;
					case 'string':
						$a = htmlspecialchars(substr($a, 0, 64)).((strlen($a) > 64) ? '...' : '');
						$args .= "\"$a\"";
						break;
					case 'array':
						$args .= 'Array('.count($a).')';
						break;
					case 'object':
						$args .= 'Object('.get_class($a).')';
						break;
					case 'resource':
						$args .= 'Resource('.strstr($a, '#').')';
						break;
					case 'boolean':
						$args .= $a ? 'True' : 'False';
						break;
					case 'NULL':
						$args .= 'Null';
						break;
					default:
						$args .= 'Unknown';
				}
			}
			$class = isset($bt['class'])?$bt['class']:'';
			$type = isset($bt['type'])?$bt['type']:'';
			$function = isset($bt['function'])?$bt['function']:'';
			$file = isset($bt['file'])?$bt['file']:'(unknown)';
			$line = isset($bt['line'])?$bt['line']:'(unknown)';

			echo "<strong>File:</strong> {$file} line {$line}<br />\n";
			echo "<strong>Function:</strong> {$class}{$type}{$function}($args)<br />\n";
			echo "<br/>\n";
		}
	}

	// Determine the application name. Use defensive code so that we
	// can handle errors during early application initialization.
	$application = null;
	if (class_exists('Registry')) {
		$application = Registry::get('application', true, null);
	}
	$applicationName = '';
	if (!is_null($application)) {
		$applicationName = $application->getName().': ';
	}

	error_log($applicationName.$reason);

	if (defined('DONT_DIE_ON_ERROR') && DONT_DIE_ON_ERROR == true) {
		// trigger an error to be catched outside the application
		trigger_error($reason);
		return;
	}

	die();
}

/**
 * Check to see if the server meets a minimum version requirement for PHP.
 * @param $version Name of version (see version_compare documentation)
 * @return boolean
 */
function checkPhpVersion($version) {
	return (version_compare(PHP_VERSION, $version) !== -1);
}

/**
 * Create a PHP4/5 compatible shallow
 * copy of the given object.
 * @param $object object
 * @return object the cloned object
 */
function &cloneObject(&$object) {
	if (checkPhpVersion('5.0.0')) {
		// We use the PHP5 clone() syntax so that PHP4 doesn't
		// raise a parse error.
		$clonedObject = clone($object);
	} else {
		// PHP4 always clones objects on assignment
		$clonedObject = $object;
	}
	return $clonedObject;
}

/**
 * Remove empty elements from an array
 * @param $array array
 * @return array
 */
function arrayClean(&$array) {
	if (!is_array($array)) return null;
	return array_filter($array, create_function('$o', 'return !empty($o);'));
}
?>
