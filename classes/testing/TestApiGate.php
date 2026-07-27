<?php

/**
 * @file classes/testing/TestApiGate.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2000-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TestApiGate
 *
 * @ingroup testing
 *
 * @brief The shared secret that enables and guards the /api/v1/_test/* namespace.
 *
 * The namespace exists ONLY when the TEST_API_KEY environment variable is present
 * in the PHP process environment. There is deliberately no configuration-file
 * fallback and no default value: a production install, which never sets the
 * variable, cannot expose these endpoints even if the files are present.
 *
 * The environment variable must actually reach PHP under whichever server manager
 * is used. `php -S` inherits the shell environment; php-fpm needs an `env[]` line
 * in the pool config; mod_php needs SetEnv. Because a silently missing variable
 * looks exactly like "the endpoint does not exist", getenv() is backed up by the
 * $_SERVER/$_ENV superglobals, which some SAPIs populate instead.
 */

namespace PKP\testing;

class TestApiGate
{
    /** The request header carrying the shared secret */
    public const HEADER = 'X-Test-Key';

    /** The environment variable carrying the shared secret */
    public const ENV_VAR = 'TEST_API_KEY';

    /**
     * The configured key, or null when the test API is not enabled.
     */
    public static function configuredKey(): ?string
    {
        $key = getenv(static::ENV_VAR);

        if (!is_string($key) || $key === '') {
            $key = $_SERVER[static::ENV_VAR] ?? $_ENV[static::ENV_VAR] ?? null;
        }

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * Whether the test API namespace should be registered at all.
     */
    public static function isEnabled(): bool
    {
        return static::configuredKey() !== null;
    }

    /**
     * Whether the key presented by a request is the configured one.
     */
    public static function accepts(?string $presentedKey): bool
    {
        $key = static::configuredKey();

        if ($key === null || !is_string($presentedKey) || $presentedKey === '') {
            return false;
        }

        return hash_equals($key, $presentedKey);
    }
}
