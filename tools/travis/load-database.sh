#!/bin/bash

# @file tools/travis/load-database.sh
#
# Copyright (c) 2014-2021 Simon Fraser University
# Copyright (c) 2010-2021 John Willinsky
# Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
#
# Script to load a copy of the database.
#
# Requires the following environment vars to be set:
# - DBTYPE: Database type (MySQL/MySQLi/PostgreSQL)
# - DBUSERNAME: Database username
# - DBPASSWORD: Database password
# - DBNAME: Database name
# - DBHOST: Database hostname
# - DATABASEDUMP: Path and filename to database dump file

set -e

# Load the database dump.
case "$DBTYPE" in
	PostgreSQL)
		zcat $DATABASEDUMP | psql --username=$DBUSERNAME --host=$DBHOST $DBNAME
		;;
	MySQL|MySQLi)
		zcat $DATABASEDUMP | mysql --user=$DBUSERNAME --password=$DBPASSWORD --host=$DBHOST $DBNAME
		;;
	*)
		echo "Unknown DBTYPE \"${DBTYPE}\"!"
		exit 1
esac
