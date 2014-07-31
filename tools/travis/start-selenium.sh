#!/bin/bash

# @file tools/travis/start-selenium.sh
#
# Copyright (c) 2014 Simon Fraser University Library
# Copyright (c) 2010-2014 John Willinsky
# Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
#
# Script to install and start a Selenium server.
#

set -xe

# Start Selenium server.
wget http://selenium-release.storage.googleapis.com/2.42/selenium-server-standalone-2.42.2.jar
nohup java -jar selenium-server-standalone-2.42.2.jar -browserSessionReuse >> selenium-output &
sleep 5 # Give time for Selenium to start
mkdir screenshots
