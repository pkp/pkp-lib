#!/bin/bash

# @file tools/travis/install-phpunit.sh
#
# Copyright (c) 2014 Simon Fraser University Library
# Copyright (c) 2010-2014 John Willinsky
# Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
#
# Script to install PHPUnit.
#

set -xe

# Install phpunit in a phar package with selenium extension already on it.
sudo rm -R /home/travis/.phpenv/versions/5.3/bin/phpunit
wget --no-check-certificate https://phar.phpunit.de/phpunit.phar
chmod +x phpunit.phar
sudo mv phpunit.phar /home/travis/.phpenv/versions/5.3/bin/phpunit
