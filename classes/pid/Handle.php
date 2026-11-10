<?php

/**
 * @file classes/pid/Handle.php
 *
 * Copyright (c) 2025-2026 Simon Fraser University
 * Copyright (c) 2025-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Handle
 *
 * @ingroup pid
 *
 * @brief Handle class
 */

namespace PKP\pid;

class Handle extends BasePid
{
    /** @copydoc BasePid::regexes */
    public const regexes = [
        // handle:12345/abcde hdl:12345/abcde https://hdl.handle.net/12345/abcde
        '/(?:handle:\s*|hdl:\s*|https?:\/\/hdl\.handle\.net\/)[0-9a-z]+(?:\.[0-9a-z]+)*\/\S+/i'
    ];

    /** @copydoc BasePid::validationRegexes */
    public const validationRegexes = [
        '/^[0-9a-z]+(?:\.[0-9a-z]+)*\/.+$/i'
    ];

    /** @copydoc BasePid::prefix */
    public const prefix = 'handle:';

    /** @copydoc BasePid::urlPrefix */
    public const urlPrefix = 'https://hdl.handle.net/';

    /** @copydoc BasePid::alternatePrefixes */
    public const alternatePrefixes = [
        'handle',
        'hdl',
        'hdl:'
    ];
}
