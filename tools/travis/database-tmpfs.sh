#!/bin/bash

# @file tools/travis/database-tmpfs.sh
#
# Copyright (c) 2014-2021 Simon Fraser University
# Copyright (c) 2010-2021 John Willinsky
# Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
#
# Script to prepare, mount and set database entrypoint as tmpfs volume.
#

set -xe

if [ "$TEST" = "mysql" ]; then
    sudo mkdir /mnt/ramdisk
    sudo mount -t tmpfs -o size=1024m tmpfs /mnt/ramdisk
    sudo service mysql stop
    sudo mv /var/lib/mysql /mnt/ramdisk
    sudo ln -s /mnt/ramdisk/mysql /var/lib/mysql
    sudo service mysql restart
fi

if [ "$TEST" = "pgsql" ]; then
    sudo mkdir /mnt/ramdisk
    sudo mount -t tmpfs -o size=1024m tmpfs /mnt/ramdisk
    sudo service postgresql stop
    sudo mv /var/lib/postgresql /mnt/ramdisk
    sudo ln -s /mnt/ramdisk/postgresql /var/lib/postgresql
    sudo service postgresql start
fi