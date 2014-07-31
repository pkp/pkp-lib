#!/bin/bash

# @file tools/travis/run-tests.sh
#
# Copyright (c) 2014 Simon Fraser University Library
# Copyright (c) 2010-2014 John Willinsky
# Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
#
# Script to run data build, unit, and integration tests.
#

set -e

DUMMYFILE=~/dummy.pdf
BASEURL="http://localhost"
DBHOST=localhost
DBNAME=ojs-ci
DBUSERNAME=ojs-ci
DBPASSWORD=ojs-ci
FILESDIR=files

# Generate a sample PDF file to use for testing.
echo "This is a test" | a2ps -o - | ps2pdf - ~/dummy.pdf

# Create the database.
if [[ "$DB" == "pgsql" ]]; then
	psql -c "CREATE DATABASE \"ojs-ci\";" -U postgres
	psql -c "CREATE USER \"ojs-ci\" WITH PASSWORD 'ojs-ci';" -U postgres
	psql -c "GRANT ALL PRIVILEGES ON DATABASE \"ojs-ci\" TO \"ojs-ci\";" -U postgres
	DBTYPE=MySQL
else if [[ "$DB" == "mysql" ]]; then
	mysql -u root -e 'CREATE DATABASE `ojs-ci` DEFAULT CHARACTER SET utf8'
	mysql -u root -e "GRANT ALL ON \`ojs-ci\`.* TO \`ojs-ci\`@localhost IDENTIFIED BY 'ojs-ci'"
	DBTYPE=PostgreSQL
else exit
fi

# Prep files
cp config.TEMPLATE.inc.php config.inc.php
mkdir ${FILESDIR}
sudo chown -R travis:www-data .

# Run data build suite
./lib/pkp/tools/runAllTests.sh -b

# Run unit test suite.
./lib/pkp/tools/runAllTests.sh -Cc
# Functional tests temporarily disabled
# - ./lib/pkp/tools/runAllTests.sh -f
