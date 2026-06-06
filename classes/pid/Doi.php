<?php

/**
 * @file classes/pid/Doi.php
 *
 * Copyright (c) 2025-2026 Simon Fraser University
 * Copyright (c) 2025-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Doi
 *
 * @ingroup pid
 *
 * @brief Doi class
 *
 * @see https://www.crossref.org/blog/dois-and-matching-regular-expressions/
 * @see https://stackoverflow.com/questions/27910/finding-a-doi-in-a-document-or-page
 */

namespace PKP\pid;

class Doi extends BasePid
{
    /** @copydoc BasePid::regexes */
    public const regexes = [
        // doi:10.1002/tox.20155 https://doi.org/10.1002/tox.20155
        '/(?:doi:\s*|https?:\/\/doi\.org\/)10[.][0-9]{4,}\/[^\s"<>]+/i'
    ];

    /** @copydoc BasePid::validationRegexes */
    public const validationRegexes = [
        '/^10[.][0-9]{4,}\/[^\s"<>]+$/i'
    ];

    /** @copydoc BasePid::prefix */
    public const prefix = 'doi:';

    /** @copydoc BasePid::urlPrefix */
    public const urlPrefix = 'https://doi.org/';

    /** @copydoc BasePid::alternatePrefixes */
    public const alternatePrefixes = [
        'doi',
        'doi.org',
        'doi.org:',
        'dx.doi.org',
        'dx.doi.org:',
        'https://dx.doi.org/',
        'http://dx.doi.org/'
    ];
}
