#!/bin/bash

#
# buildjs.sh
#
# Copyright (c) 2010-2011 John Willinsky
# Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
#
# Script to check and minimize JavaScript for distribution.
#
# Requirements:
# - Requires Python/Closure Linter and Java/Closure Compiler, see
#   <http://code.google.com/closure>. Please download the compiler.jar
#   from there. Expects Closure's compiler.jar file in '~/bin'. If you want to put it
#   into a different directory then please change the TOOL_PATH variable below.
#   Please see the Closure Linter documentation for installation instructions
#   of that tool.
#
# - Requires jslint4java, see <http://code.google.com/p/jslint4java/>. Expects the
#   jslint4java.jar (must be renamed!) in the same path as the Closure compiler,
#   i.e. in TOOL_PATH as configured below.
#
# - This tool expects to be run from the application's main directory.
#
# Usage: lib/pkp/tools/build.sh
#

### Configuration ###

TOOL_PATH=~/bin

JS_OUTPUT='lib/pkp/js/pkp.min.js'


### Start Processing ###
echo >&2
echo "Starting PKP JavaScript builder." >&2
echo "Copyright (c) 2010-2011 John Willinsky" >&2


### Checking Requirements ###
MISSING_REQUIREMENT=''
if [ -z `which gjslint` ]; then
	echo >&2
	echo "Google Closure Linter not found in PATH. Please go" >&2
	echo "to <http://code.google.com/closure> and make sure" >&2
	echo "that you correctly install the tool before you run" >&2
	echo "buildjs.sh." >&2
	MISSING_REQUIREMENT='gjslint'
fi

if [ ! -e "$TOOL_PATH/jslint4java.jar" ]; then
	echo >&2
	echo "JSLint4Java must be installed in the '$TOOL_PATH'" >&2
	echo "directory. Please download the tool from" >&2
	echo "<http://code.google.com/p/jslint4java/>," >&2
	echo "rename it to jslint4java.jar and try again." >&2
	MISSING_REQUIREMENT='jslint4java'
fi

if [ ! -e "$TOOL_PATH/compiler.jar" ]; then
	echo >&2
	echo "Google Closure Compiler not found in '$TOOL_PATH'" >&2
	echo "Please go to <http://code.google.com/closure> and" >&2
	echo "download the tool. Then try again." >&2
	MISSING_REQUIREMENT='closure'
fi

if [ -n "$MISSING_REQUIREMENT" ]; then
	echo >&2
	echo "Exiting!" >&2
	exit 1
fi
echo >&2

# A list with all files to be compiled and minified. Expects
# a complete list of script files in minifiedScripts.tpl.
COMPILE_FILES=$(sed -nr '/<script type="text\/javascript"/ { s%^.*src="\{\$baseUrl\}/([^"]+)".*$%\1%p }' templates/common/minifiedScripts.tpl)

# FIXME: For now we only check classes as the other
# files contain too many errors to be fixed right now.
LINT_FILES=`echo "$COMPILE_FILES" | egrep -v '^lib/pkp/js/(lib|functions)'`

# Create a working directory in the cache
WORKDIR=`mktemp -d` || { echo "The working directory could not be created!"; exit 1; }

# Show a list of the files we are going to lint.
echo "Lint..." >&2
echo "Lint..." >"$WORKDIR/.compile-warnings.out"
for JS_FILE in $LINT_FILES; do
	echo -n "...$JS_FILE" >&2
	echo "...$JS_FILE"

	# Prepare file for gjslint and compiler check:
	# - transforms whitespace to comply with Google style guide
	# - wraps @extends type in curly braces to comply with Google style guide.
	# - works around http://code.google.com/p/closure-compiler/issues/detail?id=61 by removing the jQuery closure.
	mkdir -p `dirname "$WORKDIR/$JS_FILE"`
	sed 's/^\t//;s/\t/  /g;s/^(function(\$) {//;s/^})(jQuery);//;s/@extends \(.*\)$/@extends {\1}/' "$JS_FILE" > "$WORKDIR/$JS_FILE"


	# Only lint file if it has been changed since last compilation.
	if [ -e "$JS_OUTPUT" -a \( "$JS_FILE" -nt "$JS_OUTPUT" \) ]; then

		#############################
		### Google Closure Linter ###
		#############################
	
		# Run gjslint on the file.
		gjslint --strict --nosummary --custom_jsdoc_tags=defgroup,ingroup,file,brief "$WORKDIR/$JS_FILE" | grep '^Line' | sed 's/^/\t/'
	
	
		##################################
		### Douglas Crockford's JSLint ###
		##################################
	
		# Run JSLint on the file:
		# - allow for loops without "hasOwnProperty()" check because we operate in an environment
		#   where additions to the Object prototype are not allowed (same as jQuery).
		# - remove jslint "unexpected space" error - whitespace checking is better done by gjslint.
		#   This is necessary to remove inconsistency between gjslint's line length checking and
		#   jslint's line break rules.
		java -jar "$TOOL_PATH/jslint4java.jar" --forin $JS_FILE | grep -v '^$' | grep -v 'Unexpected space before ' | sed 's/^/\t/'
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
LINT_FILES=`echo "$LINT_FILES" | sed "s%^%$WORKDIR/%" | tr '\n' ' ' | sed -r 's/ $//;s/(^| )/ --js /g'`

# Run Closure - first pass to check with transformed files.
echo >> "$WORKDIR/.compile-warnings.out"
echo "Compile (Check)..." >> "$WORKDIR/.compile-warnings.out"
echo "Compile (Check)..." >&2
java -jar "$TOOL_PATH/compiler.jar" --externs lib/pkp/tools/closure-externs.js --externs lib/pkp/tools/closure-externs-check-only.js --jscomp_warning visibility --warning_level VERBOSE $LINT_FILES --js_output_file /dev/null 2>&1 | sed 's/^/\t/' >>"$WORKDIR/.compile-warnings.out"

# Only minify when there were no warnings.
if [ -n "`cat $WORKDIR/.compile-warnings.out | grep '^	'`" ]; then
	# Issue warnings.
	less "$WORKDIR/.compile-warnings.out"
	echo >&2
	echo "Found Errors! Not minified."
	echo "Exiting!"
else
	# Show the list of files we are going to compile:
	echo >&2
	echo "Compile (Minify)..." >&2
	echo "$COMPILE_FILES" | sed 's/^/.../' >&2
	
	# Transform file list into Closure input parameter list.
	COMPILE_FILES=`echo "$COMPILE_FILES" | tr '\n' ' ' | sed -r 's/ $//;s/(^| )/ --js /g'`
	
	# Run Closure - second pass to minify
	java -jar "$TOOL_PATH/compiler.jar" --externs lib/pkp/tools/closure-externs.js --jscomp_off checkTypes --warning_level VERBOSE $COMPILE_FILES --js_output_file "$JS_OUTPUT" 2>&1 
	echo >&2
	
	echo "Please don't forget to set enable_minified=On in your config.inc.php." >&2
	echo >&2
	echo "Done!" >&2
fi

# Remove the temporary directory.
rm -r "$WORKDIR"

