#!/bin/bash

# @file tools/travis/validate-json.sh
#
# Copyright (c) 2014-2021 Simon Fraser University
# Copyright (c) 2010-2021 John Willinsky
# Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
#
# Script to validate all JSON files in the repository (unless excluded).
#

set -e # Fail on first error

# Collects all languages
declare -A LANGUAGES=()
for LANGUAGE in lib/pkp/locale/* lib/pkp/plugins/*/*/locale/* locale/* plugins/*/*/locale/*; do
    LANGUAGES[$(basename $LANGUAGE)]=1
done
LANGUAGE_SET=${!LANGUAGES[*]}

for LANGUAGE in $LANGUAGE_SET; do
    # Look for syntax issues in the locale files
    echo "Validating the syntax for the $LANGUAGE locale files..."
    find lib/pkp/locale lib/pkp/plugins/*/*/locale locale plugins/*/*/locale -type f -path "*/$LANGUAGE/*.po" -print0 | xargs -0 -n1 msgfmt -c

    # Look for duplicated locale entries
    echo "Searching for duplicated locale keys in the language $LANGUAGE..."
    readarray -d '' FILES < <(find lib/pkp/locale lib/pkp/plugins/*/*/locale -type f -path "*/$LANGUAGE/*.po" -print0)
    if [ ${#FILES[@]} -gt 1 ]
    then
        OUTPUT=$(msgcomm --add-location --no-wrap --omit-header --more-than=1 ${FILES[@]})
        if [ ${#OUTPUT} -gt 0 ]
        then
            echo -e "Found duplicated locale key:\n$OUTPUT"
            exit 1
        fi
    fi
    break
done
