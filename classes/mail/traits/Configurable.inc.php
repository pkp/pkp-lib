<?php

/**
 * @file mail/Configurable.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Configurable
 * @ingroup mail
 *
 * @brief trait to support Mailable's name and description displayed in the UI
 */

namespace PKP\mail\traits;

use Exception;

trait Configurable
{
    /**
     * Retrieve localized Mailable's name
     * @throws Exception
     */
    public static function getName(): string
    {
        if (is_null(static::$name)) {
            throw new Exception('Configurable mailable created without a name.');
        }

        return __(static::$name);
    }

    /**
     * Retrieve localized Mailable's description
     * @throws Exception
     */
    public static function getDescription(): string
    {
        if (is_null(static::$description)) {
            throw new Exception('Configurable mailable created without a description.');
        }

        return __(static::$description);
    }

    /**
     * Retrieve a unique Mailable's ID
     */
    public static function getId(): string
    {
        return str_replace('\\', '-', static::class);
    }
}
