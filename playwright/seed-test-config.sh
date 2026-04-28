#!/bin/sh
# Cold-boot seed for config.test.inc.php — runs from
# config-factory.js's webServer.command. Idempotent: skips if the file
# already exists. Points [email] at Mailpit (127.0.0.1:1025) so the
# pkpMail fixture sees test-action mail.
#
# Verifies each substitution actually landed; aborts loudly if
# config.TEMPLATE.inc.php has drifted (e.g. whitespace shift on
# `default = sendmail`) so the failure is "webServer didn't start"
# instead of "Mailpit asserts time out 10 minutes later".
set -e

if [ -f config.test.inc.php ]; then
    exit 0
fi

cp config.TEMPLATE.inc.php config.test.inc.php
sed -i.bak -E '
    s/^default = sendmail$/default = smtp/
    s/^; smtp = On$/smtp = On/
    s/^; smtp_server = mail\.example\.com$/smtp_server = 127.0.0.1/
    s/^; smtp_port = 25$/smtp_port = 1025/
' config.test.inc.php
rm -f config.test.inc.php.bak

# Each line must be present after the seed; if not, the template drifted.
for pattern in \
    '^default = smtp$' \
    '^smtp = On$' \
    '^smtp_server = 127\.0\.0\.1$' \
    '^smtp_port = 1025$'
do
    if ! grep -qE "$pattern" config.test.inc.php; then
        echo "ERROR: config.test.inc.php seed failed — pattern '$pattern' not found." >&2
        echo "config.TEMPLATE.inc.php may have drifted; update lib/pkp/playwright/seed-test-config.sh." >&2
        exit 1
    fi
done
