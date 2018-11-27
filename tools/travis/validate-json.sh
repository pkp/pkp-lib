#!/bin/bash

# @file tools/travis/validate-json.sh
#
# Copyright (c) 2014-2018 Simon Fraser University
# Copyright (c) 2010-2018 John Willinsky
# Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
#
# Script to validate all JSON files in the repository (unless excluded).
#

set -xe # Fail on first error

# Search for all JSON files in the current directory
REPOSITORY_DIR="."

npm install jsonlint -g
for name in `find $REPOSITORY_DIR -name \*.json | fgrep -v -f $REPOSITORY_DIR/tools/jsonlint-exclusions.txt`; do
	jsonlint -q $name
done
