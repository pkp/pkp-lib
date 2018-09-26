#!/bin/bash

# @file tools/travis/prepare-tests.sh
#
# Copyright (c) 2014-2018 Simon Fraser University
# Copyright (c) 2010-2018 John Willinsky
# Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
#
# Script to prepare the environment for the test suite.
#

set -xe

export DUMMY_PDF=~/dummy.pdf
export DUMMY_ZIP=~/dummy.zip
export BASEURL="http://localhost"
export DBHOST=localhost
export DBNAME=ojs-ci
export DBUSERNAME=ojs-ci
export DBPASSWORD=ojs-ci
export FILESDIR=files
export DATABASEDUMP=~/database.sql.gz

# Install required software
sudo apt-get install -q -y a2ps libbiblio-citation-parser-perl libhtml-parser-perl

# Generate sample files to use for testing.
echo "This is a test" | a2ps -o - | ps2pdf - ${DUMMY_PDF} # PDF format
zip ${DUMMY_ZIP} ${DUMMY_PDF} # Zip format; add PDF dummy as contents

# Create the database.
if [[ "$TEST" == "pgsql" ]]; then
	sudo service postgresql start
	psql -c "CREATE DATABASE \"ojs-ci\";" -U postgres
	psql -c "CREATE USER \"ojs-ci\" WITH PASSWORD 'ojs-ci';" -U postgres
	psql -c "GRANT ALL PRIVILEGES ON DATABASE \"ojs-ci\" TO \"ojs-ci\";" -U postgres
	echo "localhost:5432:ojs-ci:ojs-ci:ojs-ci" > ~/.pgpass
	chmod 600 ~/.pgpass
	export DBTYPE=PostgreSQL
elif [[ "$TEST" == "mysql" ]]; then
	mysql -u root -e 'CREATE DATABASE `ojs-ci` DEFAULT CHARACTER SET utf8'
	mysql -u root -e "GRANT ALL ON \`ojs-ci\`.* TO \`ojs-ci\`@localhost IDENTIFIED BY 'ojs-ci'"
	if [[ ${TRAVIS_PHP_VERSION:0:2} == "7." ]]; then
		export DBTYPE=MySQLi
	else
		export DBTYPE=MySQL
	fi
fi

# Prep files
cp config.TEMPLATE.inc.php config.inc.php
sed -i -e "s/enable_cdn = On/enable_cdn = Off/" config.inc.php # Disable CDN use
mkdir ${FILESDIR}

