#!/bin/bash

#
# buildjs.sh
#
# Copyright (c) 2003-2010 John Willinsky
# Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
#
# Script to check and minimize JavaScript for distribution.
#
# NB: requires Java and Google Closure, see
# <http://code.google.com/closure/compiler/>. Please download the compiler.jar
# from there.
#
# NB: Expects Closure's compiler.jar file in '~/bin'. If you want to put it
# into a different directory then please change the CLOSURE_PATH variable below.
# 
# NB: This tool expects to be run from the application's main directory.
#
# Usage: lib/pkp/tools/build.sh
#

CLOSURE_PATH=~/bin

JS_OUTPUT='lib/pkp/js/pkp.min.js'

JS_FILES=`cat <<-'EOF'
	lib/pkp/js/lib/jquery/plugins/jquery.form.js
	lib/pkp/js/lib/jquery/plugins/jquery.cookie.js
	lib/pkp/js/lib/jquery/plugins/jquery.imgpreview.js
	lib/pkp/js/lib/jquery/plugins/jquery.pnotify.js
	lib/pkp/js/lib/jquery/plugins/jquery.tag-it.js
	lib/pkp/js/classes/form.js
	lib/pkp/js/classes/math.js
	lib/pkp/js/classes/modal.js
	lib/pkp/js/functions/fontController.js
	lib/pkp/js/functions/general.js
	lib/pkp/js/functions/jqueryValidatorI18n.js
	lib/pkp/js/functions/listbuilder.js
	lib/pkp/js/functions/modal.js
EOF`

# Transform file list into Closure input parameter list.
JS_FILES=`echo "$JS_FILES" | tr '\n' ' ' | sed -r 's/ $//;s/(^| )/ --js /g'`

# Run Closure.
java -jar "$CLOSURE_PATH/compiler.jar" $JS_FILES --js_output_file "$JS_OUTPUT"
