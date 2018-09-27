#!/bin/bash

# @file tools/travis/dump-database.sh
#
# Copyright (c) 2014-2018 Simon Fraser University
# Copyright (c) 2010-2018 John Willinsky
# Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
#
# Script to dump a copy of the database.
#

set -xe

# Dump the completed database.
if [[ "$TEST" == "pgsql" ]]; then
	pg_dump --clean --username=$DBUSERNAME --host=$DBHOST $DBNAME | gzip -9 > $DATABASEDUMP
elif [[ "$TEST" == "mysql" ]]; then
	mysqldump --user=$DBUSERNAME --password=$DBPASSWORD --host=$DBHOST $DBNAME | gzip -9 > $DATABASEDUMP
fi
