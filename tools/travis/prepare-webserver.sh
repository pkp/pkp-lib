#!/bin/bash

# @file tools/travis/prepare-webserver.sh
#
# Copyright (c) 2014-2018 Simon Fraser University
# Copyright (c) 2010-2018 John Willinsky
# Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
#
# Script to prepare and start the PHP internal webserver for Travis testing.
#

set -xe

# Add our PHP configuration variables to the default configuration.
PATH_TO_PHPENV=$(which phpenv)
if [ -x "$PATH_TO_PHPENV" ] ; then
	phpenv config-add lib/pkp/tools/travis/php.ini
else
	echo "(Skipping phpenv add)"
fi

# This script runs as the travis user, so cannot bind to port 80. To work
# around this, we use socat to forward requests from port 80 to port 8080.
sudo socat TCP-LISTEN:80,fork,reuseaddr TCP:localhost:8080 &

# Run the PHP internal server on port 8080.
php -S 127.0.0.1:8080 -t . >& access.log &
