#!/bin/bash

# @file tools/travis/prepare-webserver.sh
#
# Copyright (c) 2014-2017 Simon Fraser University
# Copyright (c) 2010-2017 John Willinsky
# Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
#
# Script to prepare the webserver for Travis testing.
#

set -xe

# Start apache and configure a virtual host.
if [[ ${TRAVIS_PHP_VERSION:0:2} == "5." ]]; then sudo apt-get install php5-curl php5-mysql php5-pgsql php5-intl php5-xsl; fi

phpenv config-add lib/pkp/tools/travis/php.ini

nohup sudo socat TCP-LISTEN:80,fork,reuseaddr TCP:localhost:8080 &
nohup php -S 127.0.0.1:8080 -t . 2>&1 > /dev/null &
