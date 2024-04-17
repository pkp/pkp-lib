#!/bin/bash

# @file tools/travis/migration/v3_4_0/prepare-logs.sh
#
# Copyright (c) 2024 Simon Fraser University
# Copyright (c) 2024 John Willinsky
# Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
#
# Script to adjust at maximum two logs to run the tests for the 3.4 migration.
#
# @todo: Remove once the 3.4 upgrade code gets dropped
#

set -e

# Get the list of logs
eventLogsFolder="${FILESDIR}/usageStats/usageEventLogs"
logFiles=("${eventLogsFolder}/"*)
archiveFolder="${FILESDIR}/usageStats/archive"
mkdir -p ${archiveFolder}

# Get the current and previous date in YMD format
today=$(date +"%Y%m%d")
yesterday=$(date -d "1 day ago" +"%Y%m%d")

# Rename the first two logs
if [ -e "${logFiles[0]}" ]; then
	mv "${logFiles[0]}" "${eventLogsFolder}/${today}.log"
fi
if [ -e "${logFiles[1]}" ]; then
	mv "${logFiles[1]}" "${eventLogsFolder}/${yesterday}.log"
fi

# Move the remaining logs to the archive folder
for file in "${logs[@]:2}"; do
    mv "${file}" "${archiveFolder}/"
done
