#!/bin/bash

# @file tools/travis/run-tests.sh
#
# Copyright (c) 2014-2021 Simon Fraser University
# Copyright (c) 2010-2021 John Willinsky
# Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
#
# Script to run data build, unit, and integration tests.
#

set -e

# Run the data build suite (integration tests).
$(npm bin)/cypress run --headless --browser chrome --config '{"specPattern":["cypress/tests/data/**/*.cy.js"]}'

# Dump the database and files before continuing. Tests may restore this to reset the
# environment.
./lib/pkp/tools/travis/dump-database.sh
tar czf ${FILESDUMP} ${FILESDIR}

# If desired, store the built dataset in https://github.com/pkp/datasets
if [[ "$TRAVIS_PULL_REQUEST" == "false" && "$SAVE_BUILD" == "true" ]]; then
      git clone https://pkp-machine-user:${GITHUB_ACCESS_KEY}@github.com/pkp/datasets
      rm -rf datasets/${APPLICATION}/${TRAVIS_BRANCH}/${TEST}
      mkdir -p datasets/${APPLICATION}/${TRAVIS_BRANCH}/${TEST}
      zcat ${DATABASEDUMP} > datasets/${APPLICATION}/${TRAVIS_BRANCH}/${TEST}/database.sql

      tar -C datasets/${APPLICATION}/${TRAVIS_BRANCH}/${TEST} -x -z -f ${FILESDUMP}
      # The geolocation DB is too big for github; do not include it
      rm -f datasets/${APPLICATION}/${TRAVIS_BRANCH}/${TEST}/files/usageStats/IPGeoDB.mmdb

      cp config.inc.php datasets/${APPLICATION}/${TRAVIS_BRANCH}/${TEST}/config.inc.php
      cp -r public datasets/${APPLICATION}/${TRAVIS_BRANCH}/${TEST}
      rm -f datasets/${APPLICATION}/${TRAVIS_BRANCH}/${TEST}/public/.gitignore
      cd datasets
      git add --all
      git commit -m "Update datasets (${TRAVIS_BRANCH})"
      git push
      cd ..
fi

# Run the pkp-lib integration tests.
$(npm bin)/cypress run --headless --browser chrome --config '{"specPattern":["lib/pkp/cypress/tests/integration/**/*.cy.js"]}'
if [ -d "cypress/tests/integration" ]; then
	# If application integration tests are provided, run them.
	$(npm bin)/cypress run --headless --browser chrome --config '{"specPattern":["cypress/tests/integration/**/*.cy.js"]}'
fi

# Run the unit tests.
./lib/pkp/tools/runAllTests.sh -CcPpdR
