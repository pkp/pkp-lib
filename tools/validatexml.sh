#!/bin/bash

#
# USAGE:
# validatexml.sh
#
# Validate all XML files in the repository (unless excluded).
#

set -e # Fail on first error

# Search for all XML files in the current directory
REPOSITORY_DIR="."

/usr/bin/xmllint --noout --valid `find $REPOSITORY_DIR -name \*.xml | fgrep -v -f $REPOSITORY_DIR/tools/xmllint-exclusions.txt`
