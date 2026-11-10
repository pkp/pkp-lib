<?php

/**
 * @file classes/pid/Url.php
 *
 * Copyright (c) 2025-2026 Simon Fraser University
 * Copyright (c) 2025-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Url
 *
 * @ingroup pid
 *
 * @brief Url class
 */

namespace PKP\pid;

class Url extends BasePid
{
    /** @copydoc BasePid::regexes */
    public const regexes = [
        '#https?://(www\.)?[-a-zA-Z0-9@:%._\+~\#=]{2,256}\.[a-z]{2,}\b([-a-zA-Z0-9@:%_\+.~\#?&//=]*)#i'
    ];

    /** @copydoc BasePid::validationRegexes */
    public const validationRegexes = [
        '/^https?:\/\/(www\.)?[-a-z0-9@:%._\+~#=]{2,256}\.[a-z]{2,}([-a-z0-9@:%_\+.~#?&\/=]*)$/i'
    ];

    /**
     * @copydoc BasePid::defaultTrimCharacters
     *
     * A trailing "/" is part of an address, e.g. a PURL namespace.
     */
    public const defaultTrimCharacters = ' ';
}
