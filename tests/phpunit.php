#!/usr/bin/env php
<?php
/**
 * PKP-specific phpunit file.
 *
 * Integrates PHPUnit with the PKP application environment
 * and enables running/debugging tests from within Eclipse or
 * other CASE tools.
 */

/* This script must not have been executed online/remotely */
if (isset($_SERVER['SERVER_NAME'])) {
	die('This script can only be executed from the command-line');
}


if (extension_loaded('xdebug')) {
    xdebug_disable();
}

if (strpos('@php_bin@', '@php_bin') === 0) {
    set_include_path(dirname(__FILE__) . PATH_SEPARATOR . get_include_path());
}

require_once 'PHPUnit/Util/Filter.php';

PHPUnit_Util_Filter::addFileToFilter(__FILE__, 'PHPUNIT');

require 'PHPUnit/TextUI/Command.php';

define('PHPUnit_MAIN_METHOD', 'PHPUnit_TextUI_Command::main');

PHPUnit_TextUI_Command::main();
?>