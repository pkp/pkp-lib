#!/bin/bash

# @file tools/travis/prepare-tests.sh
#
# Copyright (c) 2014-2021 Simon Fraser University
# Copyright (c) 2010-2021 John Willinsky
# Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
#
# Script to prepare the environment for the test suite.
#

set -e

# Set some environment variables.
export BASEURL="http://localhost" # This is the URL to the installation directory.
export DBHOST=localhost # Database hostname
export DBNAME=${APPLICATION}-ci # Database name
export DBUSERNAME=${APPLICATION}-ci # Database username
export DBPASSWORD=${APPLICATION}-ci # Database password
export FILESDIR=files # Files directory (relative to application directory -- do not do this in production!)
export DATABASEDUMP=~/database.sql.gz # Path and filename where a database dump can be created/accessed
export FILESDUMP=~/files.tar.gz # Path and filename where a database dump can be created/accessed

# Install required software
sudo apt-get install -q -y libbiblio-citation-parser-perl libhtml-parser-perl

# Create the database and grant permissions.
if [[ "$TEST" == "pgsql" ]]; then
	sudo service postgresql start
	psql -c "CREATE DATABASE \"${DBNAME}\";" -U postgres
	psql -c "CREATE USER \"${DBUSERNAME}\" WITH PASSWORD '${DBPASSWORD}';" -U postgres
	psql -c "GRANT ALL PRIVILEGES ON DATABASE \"${DBNAME}\" TO \"${DBUSERNAME}\";" -U postgres
	echo "${DBHOST}:5432:${DBNAME}:${DBUSERNAME}:${DBPASSWORD}" > ~/.pgpass
	chmod 600 ~/.pgpass
	export DBTYPE=PostgreSQL
elif [[ "$TEST" == "mysql" ]]; then
	sudo service mysql start
	sudo mysql -u root -e "CREATE DATABASE \`${DBNAME}\` DEFAULT CHARACTER SET utf8"
	sudo mysql -u root -e "CREATE USER \`${DBUSERNAME}\`@${DBHOST} IDENTIFIED BY '${DBPASSWORD}'"
	sudo mysql -u root -e "GRANT ALL ON \`${DBNAME}\`.* TO \`${DBUSERNAME}\`@localhost WITH GRANT OPTION"
	export DBTYPE=MySQLi
elif [[ "$TEST" == "mariadb" ]]; then
	sudo service mysql stop
	sudo apt-get remove --purge mysql-server mysql-client mysql-common
	sudo apt-get install -q -y mariadb-server
	sudo mysql_install_db --user=mysql --basedir=/usr --datadir=/var/lib/mysql
	sudo service mariadb start
	sudo mysql -u root -e "CREATE DATABASE \`${DBNAME}\` DEFAULT CHARACTER SET utf8"
	sudo mysql -u root -e "CREATE USER \`${DBUSERNAME}\`@${DBHOST} IDENTIFIED BY '${DBPASSWORD}'"
	sudo mysql -u root -e "GRANT ALL ON \`${DBNAME}\`.* TO \`${DBUSERNAME}\`@localhost WITH GRANT OPTION"
	export DBTYPE=MySQLi
fi

# Use the template configuration file.
cp config.TEMPLATE.inc.php config.inc.php

# Make the files directory (this will be files_dir in config.inc.php after installation).
mkdir --parents ${FILESDIR}

# Make the required environment variables available to Cypress
export CYPRESS_DBTYPE=${DBTYPE}
cp cypress.travis.env.json cypress.env.json

set +e
