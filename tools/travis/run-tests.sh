#!/bin/bash

# @file tools/travis/run-tests.sh
#
# Copyright (c) 2014 Simon Fraser University Library
# Copyright (c) 2010-2014 John Willinsky
# Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
#
# Script to run data build, unit, and integration tests.
#

set -xe

export DUMMYFILE=~/dummy.pdf
export BASEURL="http://localhost"
export DBHOST=localhost
export DBNAME=ojs-ci
export DBUSERNAME=ojs-ci
export DBPASSWORD=ojs-ci
export FILESDIR=files
export DATABASEDUMP=~/database.sql.gz

# Install required software
sudo apt-get install a2ps libbiblio-citation-parser-perl

# Generate a sample PDF file to use for testing.
echo "This is a test" | a2ps -o - | ps2pdf - ~/dummy.pdf

# Create the database.
if [[ "$TEST" == "pgsql" ]]; then
	psql -c "CREATE DATABASE \"ojs-ci\";" -U postgres
	psql -c "CREATE USER \"ojs-ci\" WITH PASSWORD 'ojs-ci';" -U postgres
	psql -c "GRANT ALL PRIVILEGES ON DATABASE \"ojs-ci\" TO \"ojs-ci\";" -U postgres
	echo "localhost:5432:ojs-ci:ojs-ci:ojs-ci" > ~/.pgpass
	chmod 600 ~/.pgpass
	export DBTYPE=PostgreSQL
elif [[ "$TEST" == "mysql" ]]; then
	mysql -u root -e 'CREATE DATABASE `ojs-ci` DEFAULT CHARACTER SET utf8'
	mysql -u root -e "GRANT ALL ON \`ojs-ci\`.* TO \`ojs-ci\`@localhost IDENTIFIED BY 'ojs-ci'"
	export DBTYPE=MySQL
fi

# Prep files
cp config.TEMPLATE.inc.php config.inc.php
mkdir ${FILESDIR}
sudo chown -R travis:www-data .

# Run data build suite
./lib/pkp/tools/runAllTests.sh -b

# Dump the completed database.
if [[ "$TEST" == "pgsql" ]]; then
	pg_dump --clean --username=$DBUSERNAME --host=$DBHOST $DBNAME | gzip -9 > $DATABASEDUMP
elif [[ "$TEST" == "mysql" ]]; then
	mysqldump --user=$DBUSERNAME --password=$DBPASSWORD --host=$DBHOST $DBNAME | gzip -9 > $DATABASEDUMP
fi


# Run unit test suite.
# (Permissions will need to be fixed; web tests run w/different user than unit)
sudo chown -R travis:www-data ${FILESDIR}
sudo chmod -R 775 ${FILESDIR}
sudo rm -f cache/*.php
./lib/pkp/tools/runAllTests.sh -CcPpf
