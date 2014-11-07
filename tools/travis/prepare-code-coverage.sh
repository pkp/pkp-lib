#!/bin/bash

# @file tools/travis/prepare-code-coverage.sh
#
# Copyright (c) 2014 Simon Fraser University Library
# Copyright (c) 2010-2014 John Willinsky
# Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
#
# Script to set up the Travis server for code coverage reports
#

set -xe

# Save the current working directory
CWD=$(pwd)

# Install selenium dependencies
cd lib/pkp/lib/phpunit-selenium
curl -sS https://getcomposer.org/installer | php
php composer.phar install
cd $CWD

# Set the php auto append/prepend scripts up
LIB_PATH="${TRAVIS_BUILD_DIR}/lib/pkp";
echo "auto_append_file = ${LIB_PATH}/lib/phpunit-selenium/PHPUnit/Extensions/SeleniumCommon/append.php" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
echo "auto_prepend_file = ${LIB_PATH}/tests/prependCoverageReport.php" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
echo "selenium_coverage_prepend_file = ${LIB_PATH}/lib/phpunit-selenium/PHPUnit/Extensions/SeleniumCommon/prepend.php" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
echo "phpunit_coverage_data_directory = ${LIB_PATH}/tests/results/coverage-tmp" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini

# Make sure the temporary and output dirs are writable by the web server
chmod a+w lib/pkp/tests/results/coverage-tmp
chmod a+w lib/pkp/tests/results/coverage-html
