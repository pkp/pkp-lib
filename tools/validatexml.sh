#!/bin/bash

#
# USAGE:
# validatexml.sh
#
# Validate all XML files in the repository (unless excluded).
#

set -e # Fail on first error

# Get the script path, in which we'll find the exclusion list
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Search for all XML files in the current directory
REPOSITORY_DIR="."

/usr/bin/xmllint --noout --valid `find $REPOSITORY_DIR -name \*.xml | fgrep -v -f $SCRIPT_DIR/xmllint-exclusions.txt`
