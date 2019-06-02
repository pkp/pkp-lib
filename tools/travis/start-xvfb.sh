#!/bin/bash

# @file tools/travis/start-xvfb.sh
#
# Copyright (c) 2014-2019 Simon Fraser University
# Copyright (c) 2010-2019 John Willinsky
# Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
#
# Install and start xvfb (X Virtual FrameBuffer) on a Travis VM environment.
# This is used to run Selenium tests because there is no X server running (it's
# headless).
#

set -xe

export DISPLAY=":99.0" # Expose the X display per the Travis xvfb init script.

# Install some xvfb requirements.
sudo apt-get install -q -y x11-xkb-utils

# Install some fonts.
echo "ttf-mscorefonts-installer msttcorefonts/accepted-mscorefonts-eula select true" | sudo debconf-set-selections
sudo apt-get install -q -y xfonts-100dpi xfonts-75dpi xfonts-scalable xfonts-cyrillic
sudo apt-get install -q -y cabextract ttf-mscorefonts-installer
mkfontdir

# Start xvfb.
/sbin/start-stop-daemon --start --quiet --pidfile /tmp/custom_xvfb_99.pid --make-pidfile --background --exec /usr/bin/Xvfb -- :99 -ac -screen 0 1920x1080x24

# Wait for xvfb to start before continuing.
until xprop -root; do sleep 1; done
