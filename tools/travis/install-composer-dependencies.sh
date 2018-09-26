#!/bin/bash

# @file tools/travis/install-composer-dependencies.sh
#
# Copyright (c) 2014-2018 Simon Fraser University
# Copyright (c) 2010-2018 John Willinsky
# Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
#
# Script to install the Composer dependencies.
#

set -xe

find . -name composer.json -exec bash -c 'composer -d"`dirname {}`" install' ";"
