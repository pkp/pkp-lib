#!/bin/bash

#
# USAGE:
# runAllTests.sh [options]
#  -J Include job tests in lib/pkp.
#  -C Include class tests in lib/pkp.
#  -P Include plugin tests in lib/pkp.
#  -j Include job tests in application.
#  -c Include class tests in application.
#  -p Include plugin tests in application.
#  -d Display debug output from phpunit.
# If no options are specified, then all tests will be executed.
#
# Some tests will certain require environment variables in order to cnfigure
# the environment. In particular...
#  BASEURL="http://localhost/omp": Full URL to base URL, excluding index.php
#  DBHOST=localhost: Hostname of database server
#  DBNAME=yyy: Database name
#  DBUSERNAME=xxx: Username for database connections
#  DBPASSWORD=zzz: Database password
#  FILESDIR=files: Pathname to use for storing server-side submission files
#  DBTYPE=MySQL: Name of database driver (MySQL or PostgreSQL)
#

set -e # Fail on first error

### Command Line Options ###

# Run all types of tests by default, unless one or more is specified
DO_ALL=1

# Various types of tests
DO_PKP_CLASSES=0
DO_PKP_PLUGINS=0
DO_PKP_JOBS=0
DO_APP_CLASSES=0
DO_APP_PLUGINS=0
DO_APP_JOBS=0
DO_COVERAGE=0
DEBUG=""

# Parse arguments
while getopts "CPcpdRJj" opt; do
	case "$opt" in
		J)	DO_ALL=0
			DO_PKP_JOBS=1
			;;
		C)	DO_ALL=0
			DO_PKP_CLASSES=1
			;;
		P)	DO_ALL=0
			DO_PKP_PLUGINS=1
			;;
		j)	DO_ALL=0
			DO_APP_JOBS=1
			;;
		c)	DO_ALL=0
			DO_APP_CLASSES=1
			;;
		p)	DO_ALL=0
			DO_APP_PLUGINS=1
			;;
		d)	DEBUG="--debug"
			;;
		R)	DO_COVERAGE=1
			;;
	esac
done
PHPUNIT='php lib/pkp/lib/vendor/phpunit/phpunit/phpunit --configuration lib/pkp/tests/phpunit.xml --testdox'

# Where to look for tests
TEST_SUITES='--testsuite '

if [ \( "$DO_ALL" -eq 1 \) -o \( "$DO_PKP_JOBS" -eq 1 \) ]; then
	TEST_SUITES="${TEST_SUITES}LibraryJobs,"
fi

if [ \( "$DO_ALL" -eq 1 \) -o \( "$DO_PKP_CLASSES" -eq 1 \) ]; then
	TEST_SUITES="${TEST_SUITES}LibraryClasses,"
fi

if [ \( "$DO_ALL" -eq 1 \) -o \( "$DO_PKP_PLUGINS" -eq 1 \) ]; then
	TEST_SUITES="${TEST_SUITES}LibraryPlugins,"
fi

if [ \( "$DO_ALL" -eq 1 \) -o \( "$DO_APP_JOBS" -eq 1 \) ]; then
	TEST_SUITES="${TEST_SUITES}ApplicationJobs,"
fi
if [ \( "$DO_ALL" -eq 1 \) -o \( "$DO_APP_CLASSES" -eq 1 \) ]; then
	TEST_SUITES="${TEST_SUITES}ApplicationClasses,"
fi

if [ \( "$DO_ALL" -eq 1 \) -o \( "$DO_APP_PLUGINS" -eq 1 \) ]; then
	TEST_SUITES="${TEST_SUITES}ApplicationPlugins,"
fi

if [ "$DO_COVERAGE" -eq 1 ]; then
	export XDEBUG_MODE=coverage
fi

$PHPUNIT $DEBUG ${TEST_SUITES%%,}

if [ "$DO_COVERAGE" -eq 1 ]; then
	cat lib/pkp/tests/results/coverage.txt
fi
