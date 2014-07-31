#!/bin/bash

# @file tools/travis/start-xvfb.sh
#
# Copyright (c) 2014 Simon Fraser University Library
# Copyright (c) 2010-2014 John Willinsky
# Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
#
# Script to install and start xvfb.
#

set -xe

# Xvfb requirements.
sudo apt-get install -y x11-xkb-utils
# Add fonts.
sudo apt-get install -y xfonts-100dpi xfonts-75dpi xfonts-scalable xfonts-cyrillic
sudo apt-get install -y defoma x-ttcidfont-conf cabextract ttf-mscorefonts-installer
sudo dpkg-reconfigure --default-priority x-ttcidfont-conf
mkfontdir
# Start Virtual Framebuffer to imitate a monitor.
sh -e /etc/init.d/xvfb start
sleep 10 # Give xvfb time to start.
