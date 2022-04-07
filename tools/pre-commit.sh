#!/bin/bash

# @file tools/pre-commit.sh
#
# Copyright (c) 2014-2021 Simon Fraser University
# Copyright (c) 2010-2021 John Willinsky
# Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
#
# A pre-commit hook to run php-cs-fixer on committed changes
#

# Run php-cs-fixer on all committed changes
git diff --name-only --diff-filter=d --cached | xargs ./lib/vendor/bin/php-cs-fixer fix --allow-risky=yes --path-mode=intersection --config=.php-cs-fixer.php -q

# Run php-cs-fixer again with --dry-run to throw an error if any files could not be automatically formatted
git diff --name-only --diff-filter=d --cached | xargs ./lib/vendor/bin/php-cs-fixer fix --allow-risky=yes --dry-run --path-mode=intersection --config=.php-cs-fixer.php

if [ $? -eq 0 ]
then
	echo -e "\n\e[32m✔ Files formatted successfully.\e[0m\n"
	git diff --name-only --diff-filter=d --cached | xargs git add -u
else
	echo -e "\n\e[31m✘ Commit aborted. Files could not be formatted.\e[0m\n"
	exit 1
fi

