#!/bin/bash

# @file tools/travis/prepare-webserver.sh
#
# Copyright (c) 2014-2018 Simon Fraser University
# Copyright (c) 2010-2018 John Willinsky
# Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
#
# Script to prepare the webserver for Travis testing.
#

set -xe

phpenv config-add lib/pkp/tools/travis/php.ini

sudo socat TCP-LISTEN:80,fork,reuseaddr TCP:localhost:8080 &
php -S 127.0.0.1:8080 -t . >& access.log &
