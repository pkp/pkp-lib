<?php

/**
 * @file classes/citation/pid/Doi.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Doi
 *
 * @ingroup citation
 *
 * @brief Doi class
 *
 * @see https://www.crossref.org/blog/dois-and-matching-regular-expressions/
 * @see https://stackoverflow.com/questions/27910/finding-a-doi-in-a-document-or-page
 */

namespace PKP\citation\pid;

class Doi extends BasePid
{
    /** @copydoc AbstractPid::regex */
    public const regexes = [
        '/doi:\s*10[.][0-9]{4,}\/[^\s"<>]+/i',
        '/https?:\/\/doi\.org\/10[.][0-9]{4,}\/[^\s"<>]+/i'
    ];

    /** @copydoc AbstractPid::prefix */
    public const prefix = 'doi:';

    /** @copydoc AbstractPid::urlPrefix */
    public const urlPrefix = 'https://doi.org/';

    /** @copydoc AbstractPid::alternatePrefixes */
    public const alternatePrefixes = [
        'doi',
        'doi.org',
        'doi.org:',
        'dx.doi.org',
        'dx.doi.org:'
    ];
}
