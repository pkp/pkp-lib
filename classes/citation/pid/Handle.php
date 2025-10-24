<?php

/**
 * @file classes/citation/pid/Handle.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Handle
 *
 * @ingroup citation
 *
 * @brief Handle class
 */

namespace PKP\citation\pid;

class Handle extends BasePid
{
    /** @copydoc AbstractPid::regex */
    public const regexes = [
        '/(?:handle|hdl):\s*\d+\/[a-zA-Z0-9\-_]+/i', // handle:12345/abcde hdl:12345/abcde
        '/https?:\/\/hdl\.handle\.net\/\d+\/[a-zA-Z0-9\-_]+/i', // https://hdl.handle.net/12345/abcde
    ];

    /** @copydoc AbstractPid::prefix */
    public const prefix = 'handle:';

    /** @copydoc AbstractPid::urlPrefix */
    public const urlPrefix = 'https://hdl.handle.net/';

    /** @copydoc AbstractPid::alternatePrefixes */
    public const alternatePrefixes = [
        'handle',
        'hdl',
        'hdl:'
    ];
}
