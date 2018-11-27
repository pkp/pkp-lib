#!/bin/bash

# @file tools/travis/validate-xml.sh
#
# Copyright (c) 2014-2018 Simon Fraser University
# Copyright (c) 2010-2018 John Willinsky
# Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
#
# Validate all XML files in the repository (unless excluded).
#

set -xe # Fail on first error

# Search for all XML files in the current directory
REPOSITORY_DIR="."

# Install libxml2-utils if not already.
dpkg -s libxml2-utils > /dev/null || sudo apt-get -q install libxml2-utils

# Lint all XML files, except those on the exclude list.
/usr/bin/xmllint --noout --valid `find $REPOSITORY_DIR -name \*.xml | fgrep -v -f $REPOSITORY_DIR/tools/xmllint-exclusions.txt`
