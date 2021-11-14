<?php
/**
 * @file tests/prependCoverageReport.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @ingroup tests
 *
 * @brief This script needs to be called via the auto_prepend_file php.ini config
 * directive. It prepares the environment for the PHPUnit Selenium code
 * coverage scripts
 *
 * @see tools/runAllTests.sh
 */
$GLOBALS['PHPUNIT_COVERAGE_DATA_DIRECTORY'] = ini_get('phpunit_coverage_data_directory');
include ini_get('selenium_coverage_prepend_file');

if (basename($_SERVER['SCRIPT_NAME']) == 'phpunit_coverage.php') {
    chdir(ini_get('phpunit_coverage_data_directory'));
}
