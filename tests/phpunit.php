#!/usr/bin/env php
<?php
/**
 * PKP-specific phpunit file.
 *
 * Integrates PHPUnit with the PKP application environment
 * and enables running/debugging tests from within Eclipse or
 * other CASE tools.
 */

// This script may not be executed remotely.
if (isset($_SERVER['SERVER_NAME'])) {
	die('This script can only be executed from the command-line');
}

if (extension_loaded('xdebug')) {
    xdebug_disable();
}

if (strpos('/usr/bin/php', '@php_bin') === 0) {
    set_include_path(dirname(__FILE__) . PATH_SEPARATOR . get_include_path());
}

require 'PHPUnit/Autoload.php';

PHPUnit_TextUI_Command::main();
?>