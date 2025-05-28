#!/bin/bash

# @file tools/buildjs.sh
#
# Copyright (c) 2014-2021 Simon Fraser University
# Copyright (c) 2010-2021 John Willinsky
# Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
#
# Script to check and minimize JavaScript for distribution.
#
# Requirements:
# - Requires Python/Closure Linter and Java/Closure Compiler, see
#   <http://code.google.com/closure>. Install this using npm.
#   Please see the Closure Linter documentation for installation instructions
#   of that tool.
#
# - Requires jslint4java, see <http://code.google.com/p/jslint4java/>. Expects the
#   jslint4java.jar (must be renamed!) in the same path as the Closure compiler,
#   i.e. in TOOL_PATH as configured below.
#
# - This tool expects to be run from the application's main directory.
#
# Usage: lib/pkp/tools/buildjs.sh [-n]
# ...where -n can be optionally specified to prevent caching.
#


### OS specific configuration ###

# Define a tab to be used inside of sed commands (sed on OSX does not recognize \t)
TAB=$'\t'

# Determine what flag to use for extended regular expressions
if [ `uname` == 'Darwin' ]; then
	EXTENDED_REGEX_FLAG='E'
else
	EXTENDED_REGEX_FLAG='r'
fi

### Configuration ###

TOOL_PATH=~/bin
CLOSURE_COMPILER_JAR=./node_modules/google-closure-compiler-java/compiler.jar

JS_OUTPUT='js/pkp.min.js'

CLOSURE_EXTERNS='
	--externs lib/pkp/tools/closure-externs.js
	--externs lib/pkp/tools/closure-externs-check-only.js
	--externs lib/pkp/tools/jquery-externs.js'


### Command Line Options ###

OPTIND=1
DO_CACHE=1
while getopts "n" opt; do
	case "$opt" in
		n)	DO_CACHE=0 # No caching
			;;
	esac
done

shift $((OPTIND-1))

### Start Processing ###
echo >&2
echo "Starting PKP JavaScript builder." >&2
echo "Copyright (c) 2014-2021 Simon Fraser University" >&2
echo "Copyright (c) 2010-2021 John Willinsky" >&2


### Checking Requirements ###
MISSING_REQUIREMENT=''
if [ ! -e "$TOOL_PATH/jslint4java.jar" ]; then
	echo >&2
	echo "JSLint4Java must be installed in the '$TOOL_PATH'" >&2
	echo "directory. Please download the tool from" >&2
	echo "<http://code.google.com/p/jslint4java/>," >&2
	echo "rename it to jslint4java.jar and try again." >&2
	MISSING_REQUIREMENT='jslint4java'
fi

if [ ! -e "$CLOSURE_COMPILER_JAR" ]; then
	echo >&2
	echo "Google Closure Compiler not found in '$CLOSURE_COMPILER_JAR'" >&2
	echo "Please run 'npm install --save google-closure-compiler' and try again." >&2
	MISSING_REQUIREMENT='closure'
fi

if [ -n "$MISSING_REQUIREMENT" ]; then
	echo >&2
	echo "Exiting!" >&2
	exit 1
fi
echo >&2

# A list with all files to be compiled and minified. Expects
# a complete list of script files in registry/minifiedScripts.txt.
COMPILE_FILES=$(sed -n '/^[^#]/p' registry/minifiedScripts.txt)

# FIXME: For now we only check classes as the other
# files contain too many errors to be fixed right now.
LINT_FILES=`echo "$COMPILE_FILES" | egrep -v '^lib/pkp/js/(lib|functions)'`

# Create a working directory in the cache
WORKDIR=`mktemp -dt tmp.XXXXXXXXXX` || { echo "The working directory could not be created\!"; exit 1; }

# Show a list of the files we are going to lint.
echo "Lint..." >&2
echo "Lint..." >"$WORKDIR/.compile-warnings.out"
for JS_FILE in $LINT_FILES; do
	echo -n "...$JS_FILE" >&2
	echo "...$JS_FILE"

	# Prepare file for compiler check:
	# - transforms whitespace to comply with Google style guide
	# - wraps @extends type in curly braces to comply with Google style guide.
	# - works around http://code.google.com/p/closure-compiler/issues/detail?id=61 by removing the jQuery closure.
	mkdir -p `dirname "$WORKDIR/$JS_FILE"`
	sed \
		-e "s/^${TAB}//" \
		-e "s/${TAB}/  /g" \
		-e 's/^(function(\$) {//' \
		-e 's/^}(jQuery));//' \
		-e 's/@extends \(.*\)$/@extends {\1}/' \
		"$JS_FILE" > "$WORKDIR/$JS_FILE"


	# Only lint file if it has been changed since last compilation.
	if [ ! \( -e "$JS_OUTPUT" \) -o \( "$JS_FILE" -nt "$JS_OUTPUT" \) -o \( "$DO_CACHE" -eq 0 \) ]; then

		##################################
		### Douglas Crockford's JSLint ###
		##################################

		# Run JSLint on the file:
		# - Allow for loops without "hasOwnProperty()" check because we operate in an environment
		#   where additions to the Object prototype are not allowed (same as jQuery).
		# - Do not alert on whitespace checking.
		# - We allow dangling underscores (_) to mark private properties and let the
		#   Closure compiler enforce it.
		# - We allow the ++ and == syntax
		# - We allow "continue"
		# - Multiple var statements in one function are allowed to reduce variable span.
		# - We allow code without the 'use strict' pragma as we need the callee property
		#   for our class framework implementation.
		java -jar "$TOOL_PATH/jslint4java.jar" --white --forin --nomen --plusplus --continue \
			--eqeq --sloppy --browser --predef pkp,jQuery,alert,tinyMCE,confirm,plupload,Promise \
			--regexp "$JS_FILE" | sed "s/^/${TAB}/"
		echo "...processed!" >&2

	else
		echo "...skipped!" >&2
	fi
done >>"$WORKDIR/.compile-warnings.out"
echo >&2


###############################
### Google Closure Compiler ###
###############################

# Transform lint file list into Closure input parameter list.
LINT_FILES=`echo "$LINT_FILES" | sed "s%^%$WORKDIR/%" | tr '\n' ' ' | sed -$EXTENDED_REGEX_FLAG 's/ $//;s/(^| )/ --js /g'`

# Run Closure - first pass to check with transformed files.
echo >> "$WORKDIR/.compile-warnings.out"
echo "Compile (Check)..." >> "$WORKDIR/.compile-warnings.out"
echo "Compile (Check)..." >&2
java -jar ${CLOSURE_COMPILER_JAR} --language_in=ECMASCRIPT5 --jscomp_warning visibility --warning_level DEFAULT \
	$CLOSURE_EXTERNS $LINT_FILES --js_output_file /dev/null 2>&1 \
	| sed "s/^/${TAB}/" >>"$WORKDIR/.compile-warnings.out"

# Only minify when there were no warnings.
if [ -n "`cat $WORKDIR/.compile-warnings.out | grep '^	' | grep -v 'Picked up _JAVA_OPTIONS'`" ]; then
	# Issue warnings. If interactive, use "less".
	case "$-" in
		*i*)	less "$WORKDIR/.compile-warnings.out" ;;
		*)	cat "$WORKDIR/.compile-warnings.out" ;;
	esac
	echo >&2
	echo "Found Errors! Not minified."
	echo "Exiting!"

	# Remove the temporary directory.
	rm -r "$WORKDIR"

	exit -1
fi

# Show the list of files we are going to compile:
echo >&2
echo "Compile (Minify)..." >&2
echo "$COMPILE_FILES" | sed 's/^/.../' >&2

# Transform file list into Closure input parameter list.
COMPILE_FILES=`echo "$COMPILE_FILES" | tr '\n' ' ' | sed -$EXTENDED_REGEX_FLAG 's/ $//;s/(^| )/ --js /g'`

# Run Closure - second pass to minify
java -jar ${CLOSURE_COMPILER_JAR} --language_in=ECMASCRIPT5 --jscomp_off checkTypes --warning_level DEFAULT $COMPILE_FILES \
	$CLOSURE_EXTERNS --js_output_file "$JS_OUTPUT" 2>&1
echo >&2

echo "Please don't forget to set enable_minified=On in your config.inc.php." >&2
echo >&2
echo "Done!" >&2

# Remove the temporary directory.
rm -r "$WORKDIR"

exit 0
