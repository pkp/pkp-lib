#!/bin/bash

#
# USAGE:
# runAllTests.sh [options]
#  -b	Include data build tests in application.
#  -C	Include class tests in lib/pkp.
#  -P	Include plugin tests in lib/pkp.
#  -c	Include class tests in application.
#  -p	Include plugin tests in application.
#  -f	Include functional tests in application.
#  -d   Display debug output from phpunit.
# If no options are specified, then all tests will be executed.
#
# Some tests will certain require environment variables in order to cnfigure
# the environment. In particular...
#  DUMMY_PDF=dummy.pdf: Path to dummy PDF file to use for document uploads
#  DUMMY_ZIP=dummy.zip: Path to dummy ZIP file to use for document uploads
#  BASEURL="http://localhost/omp": Full URL to base URL, excluding index.php
#  DBHOST=localhost: Hostname of database server
#  DBNAME=yyy: Database name
#  DBUSERNAME=xxx: Username for database connections
#  DBPASSWORD=zzz: Database password
#  FILESDIR=files: Pathname to use for storing server-side submission files
#  DBTYPE=MySQL: Name of database driver (MySQL or PostgreSQL)
#  TIMEOUT=30: Selenium timeout; optional, 30 seconds by default
#

set -xe # Fail on first error

# We recommend using Travis (https://travis-ci.org/) for continuous-integration
# based testing. Review the Travis configuration file (.travis.yml) as a
# reference for running the test locally, should you choose to do so.
#
# The tests include an integration test suite that builds a data environment
# from scratch, including the installation process. (This is the "-b" flag to
# this script; this is also executed in the Travis environment.)

# Identify the tests directory.
TESTS_DIR=`readlink -f "lib/pkp/tests"`

# Shortcuts to the test environments.
TEST_CONF1="--configuration $TESTS_DIR/phpunit-env1.xml"
TEST_CONF2="--configuration $TESTS_DIR/phpunit-env2.xml"

### Command Line Options ###

# Run all types of tests by default, unless one or more is specified
DO_ALL=1

# Various types of tests
DO_APP_DATA=0
DO_PKP_CLASSES=0
DO_PKP_PLUGINS=0
DO_APP_CLASSES=0
DO_APP_PLUGINS=0
DO_APP_FUNCTIONAL=0
DO_COVERAGE=0
DEBUG=""

# Parse arguments
while getopts "bCPcpfdH" opt; do
	case "$opt" in
		b)	DO_ALL=0
			DO_APP_DATA=1
			;;
		C)	DO_ALL=0
			DO_PKP_CLASSES=1
			;;
		P)	DO_ALL=0
			DO_PKP_PLUGINS=1
			;;
		c)	DO_ALL=0
			DO_APP_CLASSES=1
			;;
		p)	DO_ALL=0
			DO_APP_PLUGINS=1
			;;
		f)	DO_ALL=0
			DO_APP_FUNCTIONAL=1
			;;
		d)	DEBUG="--debug"
			;;
	esac
done
phpunit='php lib/pkp/lib/vendor/phpunit/phpunit/phpunit'
if [ \( "$DO_ALL" -eq 1 \) -o \( "$DO_APP_DATA" -eq 1 \) ]; then
	$phpunit $DEBUG $TEST_CONF1 -v --stop-on-failure --stop-on-skipped tests/data
fi

if [ \( "$DO_ALL" -eq 1 \) -o \( "$DO_PKP_CLASSES" -eq 1 \) ]; then
	$phpunit $DEBUG $TEST_CONF1 -v lib/pkp/tests/classes
fi

if [ \( "$DO_ALL" -eq 1 \) -o \( "$DO_PKP_PLUGINS" -eq 1 \) ]; then
	$phpunit $DEBUG $TEST_CONF2 -v lib/pkp/plugins
fi

if [ \( "$DO_ALL" -eq 1 \) -o \( "$DO_APP_CLASSES" -eq 1 \) ]; then
	$phpunit $DEBUG $TEST_CONF1 -v tests/classes
fi

if [ \( "$DO_ALL" -eq 1 \) -o \( "$DO_APP_PLUGINS" -eq 1 \) ]; then
	find plugins -name tests -maxdepth 3 -type d -exec $phpunit $DEBUG $TEST_CONF2 -v "{}" ";"
fi

if [ \( "$DO_ALL" -eq 1 \) -o \( "$DO_APP_FUNCTIONAL" -eq 1 \) ]; then
	$phpunit $DEBUG $TEST_CONF1 -v tests/functional
fi
