<?php

/**
 * @file classes/pid/Ark.php
 *
 * Copyright (c) 2025-2026 Simon Fraser University
 * Copyright (c) 2025-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Ark
 *
 * @ingroup pid
 *
 * @brief Ark class
 *
 * @see https://arks.org/about/
 */

namespace PKP\pid;

class Ark extends BasePid
{
    /** @copydoc BasePid::regexes */
    public const regexes = [
        // ark:/12345/abc123 https://n2t.net/ark:/12345/abc123
        '/(?:https?:\/\/n2t\.net\/)?ark:\/\d{5,}(?:\/\S+)+/i'
    ];

    /** @copydoc BasePid::validationRegexes */
    public const validationRegexes = [
        '/^ark:\/\d{5,}(\/.+)+$/i'
    ];

    /** @copydoc BasePid::urlPrefix */
    public const urlPrefix = 'https://n2t.net/';

    /** @copydoc BasePid::alternatePrefixes */
    public const alternatePrefixes = [
        'https://n2t.net/',
    ];
}
