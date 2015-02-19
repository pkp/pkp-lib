#!/bin/bash

# @file tools/travis/config-ff-profile.sh
#
# Copyright (c) 2014-2015 Simon Fraser University Library
# Copyright (c) 2010-2015 John Willinsky
# Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
#
# Script to configure a Firefox profile.
#

set -xe

# Create folder where Firefox will download the files.
mkdir ~/downloads

# Create the profile folders.
mkdir ~/.mozilla
mkdir ~/.mozilla/firefox
mkdir ~/.mozilla/firefox/selenium

# Create the profiles file definition.
printf "[Profile1]\nName=selenium\nPath=selenium" > ~/.mozilla/firefox/profiles.ini

# Move profile files.
cp lib/pkp/tests/browserProfiles/firefox/* ~/.mozilla/firefox/selenium/
