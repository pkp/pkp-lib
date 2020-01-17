#!/bin/bash

# @file tools/travis/run-tests.sh
#
# Copyright (c) 2014-2019 Simon Fraser University
# Copyright (c) 2010-2019 John Willinsky
# Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
#
# Script to run data build, unit, and integration tests.
#

set -xe

# Run the data build suite (integration tests).
# Environment variables used in Cypress need prefix.
$(npm bin)/cypress run --headless --browser chrome

# Dump the database before continuing. Some tests restore this to reset the
# environment.
./lib/pkp/tools/travis/dump-database.sh

# Run the rest of the test suite (unit tests etc).
sudo rm -f cache/*.php

# Run the tests.
./lib/pkp/tools/runAllTests.sh -CcPpd
