#!/bin/bash

# @file tools/travis/prepare-webserver.sh
#
# Copyright (c) 2014 Simon Fraser University Library
# Copyright (c) 2010-2014 John Willinsky
# Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
#
# Script to prepare the webserver for Travis testing.
#

set -xe

# Start apache and configure a virtual host.
sudo apt-get update > /dev/null
sudo apt-get install -y --force-yes apache2 libapache2-mod-php5 php5-curl php5-mysql php5-pgsql php5-intl
sudo sed -i -e "s,/var/www,$(pwd)/,g" /etc/apache2/sites-available/default
sudo sed -i -e "s,\${APACHE_LOG_DIR},$(pwd),g" /etc/apache2/sites-available/default
sudo /etc/init.d/apache2 restart
