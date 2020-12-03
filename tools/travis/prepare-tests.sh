#!/bin/bash

# @file tools/travis/prepare-tests.sh
#
# Copyright (c) 2014-2020 Simon Fraser University
# Copyright (c) 2010-2020 John Willinsky
# Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
#
# Script to prepare the environment for the test suite.
#

set -xe

# Set some environment variables.
export BASEURL="http://localhost" # This is the URL to the OJS installation directory.
export DBHOST=localhost # Database hostname
export DBNAME=ojs-ci # Database name
export DBUSERNAME=ojs-ci # Database username
export DBPASSWORD=ojs-ci # Database password
export FILESDIR=files # Files directory (relative to OJS installation -- do not do this in production!)
export DATABASEDUMP=~/database.sql.gz # Path and filename where a database dump can be created/accessed

# Install required software
sudo apt-get install -q -y libbiblio-citation-parser-perl libhtml-parser-perl

# Create the database and grant permissions.
if [[ "$TEST" == "pgsql" ]]; then
	sudo service postgresql start
	psql -c "CREATE DATABASE \"ojs-ci\";" -U postgres
	psql -c "CREATE USER \"ojs-ci\" WITH PASSWORD 'ojs-ci';" -U postgres
	psql -c "GRANT ALL PRIVILEGES ON DATABASE \"ojs-ci\" TO \"ojs-ci\";" -U postgres
	echo "${DBHOST}:5432:${DBNAME}:${DBUSERNAME}:${DBPASSWORD}" > ~/.pgpass
	chmod 600 ~/.pgpass
	export DBTYPE=PostgreSQL
elif [[ "$TEST" == "mysql" ]]; then
	sudo service mysql start
	sudo mysql -u root -e 'CREATE DATABASE `ojs-ci` DEFAULT CHARACTER SET utf8'
	sudo mysql -u root -e "CREATE USER \`ojs-ci\`@localhost IDENTIFIED BY 'ojs-ci'"
	sudo mysql -u root -e "GRANT ALL ON \`ojs-ci\`.* TO \`ojs-ci\`@localhost WITH GRANT OPTION"
	export DBTYPE=MySQLi
fi

# Use the template configuration file.
cp config.TEMPLATE.inc.php config.inc.php

# Use ENABLE_CDN = 1 to prevent default disabling of the CDN for test purposes.
if [[ "$ENABLE_CDN" != "1" ]]; then
	sed -i -e "s/enable_cdn = On/enable_cdn = Off/" config.inc.php
fi
# Use DISABLE_PATH_INFO = 1 to turn on disable_path_info mode in config.inc.php.
if [[ "$DISABLE_PATH_INFO" == "1" ]]; then
	sed -i -e "s/disable_path_info = Off/disable_path_info = On/" config.inc.php
fi

# Disable CDN usage.
sed -i -e "s/enable_cdn = On/enable_cdn = Off/" config.inc.php

# Make the files directory (this will be files_dir in config.inc.php after installation).
mkdir --parents ${FILESDIR}

# Cypress requires a CYPRESS_ prefix on environment variables
export CYPRESS_baseUrl=${BASEURL}
export CYPRESS_DBTYPE=${DBTYPE}
export CYPRESS_DBUSERNAME=${DBUSERNAME}
export CYPRESS_DBNAME=${DBNAME}
export CYPRESS_DBPASSWORD=${DBPASSWORD}
export CYPRESS_DBHOST=${DBHOST}
export CYPRESS_FILESDIR=${FILESDIR}

set +e
