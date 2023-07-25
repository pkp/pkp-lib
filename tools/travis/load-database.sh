#!/bin/bash

# @file tools/travis/load-database.sh
#
# Copyright (c) 2014-2023 Simon Fraser University
# Copyright (c) 2010-2023 John Willinsky
# Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
#
# Script to load a fresh copy of the database.
#
# Requires the following environment vars to be set:
# - DBTYPE: Database type (MySQL/MySQLi/PostgreSQL)
# - DBUSERNAME: Database username
# - DBPASSWORD: Database password
# - DBNAME: Database name
# - DBHOST: Database hostname
# - DATABASEDUMP: Path and filename to database dump file

set -e

# Dump the completed database.
case "$DBTYPE" in
	PostgreSQL)
		# Database drop and recreate is DISABLED to avoid conflicts with local testing setups.
		# psql -c "DROP DATABASE \"${DBNAME}\";" -U postgres
		# psql -c "CREATE DATABASE \"${DBNAME}\";" -U postgres
		zcat ${DATABASEDUMP} | psql --username=${DBUSERNAME} --host=${DBHOST} ${DBNAME}
		;;
	MySQL|MySQLi)
		# Database drop and recreate is DISABLED to avoid conflicts with local testing setups.
		# sudo mysql -u root -e "DROP DATABASE \`${DBNAME}\`"
		# sudo mysql -u root -e "CREATE DATABASE \`${DBNAME}\` DEFAULT CHARACTER SET utf8"
		zcat ${DATABASEDUMP} | mysql --user=${DBUSERNAME} --password=${DBPASSWORD} --host=${DBHOST} ${DBNAME}
		;;
	*)
		echo "Unknown DBTYPE \"${DBTYPE}\"!"
		exit 1
esac
