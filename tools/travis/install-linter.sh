#!/bin/bash

# @file tools/travis/install-linter.sh
#
# Copyright (c) 2014-2021 Simon Fraser University
# Copyright (c) 2010-2021 John Willinsky
# Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
#
# Script to install the JS linter.
#

set -e

# Install python, linter, closure compiler stuff
sudo pip install six
sudo pip install https://github.com/google/closure-linter/zipball/master

# Install jslint4java
wget "https://storage.googleapis.com/google-code-archive-downloads/v2/code.google.com/jslint4java/jslint4java-2.0.2-dist.zip"
unzip jslint4java-2.0.2-dist.zip
mv jslint4java-2.0.2/jslint4java-2.0.2.jar ~/bin/jslint4java.jar
