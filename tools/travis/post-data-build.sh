#!/bin/bash

# @file tools/travis/post-data-build.sh
#
# Copyright (c) 2014-2015 Simon Fraser University Library
# Copyright (c) 2010-2015 John Willinsky
# Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
#
# Script to upload the results of the data build to PKP server
#

set -e

if [[ -n "$COVERAGE_UPLOAD_SECRET" ]]; then
	echo "Uploading test data pkp.sfu.ca."
	export SSHPASS=$COVERAGE_UPLOAD_SECRET

	# Prepare a directory with the contents of the dump
	mkdir builddump
	mkdir builddump/${DBTYPE}
	cp ${DATABASEDUMP} builddump/${DBTYPE}/db-${TRAVIS_REPO_SLUG}-${TRAVIS_BRANCH}.sql.gz
	tar czf builddump/${DBTYPE}/files-${TRAVIS_REPO_SLUG}-${TRAVIS_BRANCH}.tar.gz ${FILES}
	
	rsync -av --rsh='sshpass -e ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -l pkp_testing' builddump/ pkp-www.lib.sfu.ca:builds/${TRAVIS_REPO_SLUG}
fi
