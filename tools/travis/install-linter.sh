#!/bin/bash

# @file tools/travis/install-linter.sh
#
# Copyright (c) 2014-2015 Simon Fraser University Library
# Copyright (c) 2010-2015 John Willinsky
# Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
#
# Script to install the JS linter.
#

set -xe

# Install python, linter, closure compiler stuff
sudo easy_install "http://closure-linter.googlecode.com/files/closure_linter-latest.tar.gz"
wget -O compiler.zip "https://closure-compiler.googlecode.com/files/compiler-20120917.zip"
unzip compiler.zip compiler.jar
mkdir ~/bin
mv compiler.jar ~/bin
wget "http://jslint4java.googlecode.com/files/jslint4java-2.0.2-dist.zip"
unzip jslint4java-2.0.2-dist.zip
mv jslint4java-2.0.2/jslint4java-2.0.2.jar ~/bin/jslint4java.jar
