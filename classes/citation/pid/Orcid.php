<?php

/**
 * @file classes/citation/pid/Orcid.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Orcid
 *
 * @ingroup citation
 *
 * @brief Orcid class
 */

namespace PKP\citation\pid;

class Orcid extends BasePid
{
    /** @copydoc AbstractPid::regex */
    public const regexes = [
        '/orcid:\s*\d{4}-\d{4}-\d{4}-\d{1,4}[0-9X]/i', // orcid:0000-0002-1694-233X
        '/https?:\/\/(www\.)?orcid\.org\/\d{4}-\d{4}-\d{4}-\d{1,4}[0-9X]/i', // https://orcid.org/0000-0002-1694-233X
    ];

    /** @copydoc AbstractPid::defaultPrefix */
    public const defaultPrefix = 'https://orcid.org/';

    /** @copydoc AbstractPid::urlPrefix */
    public const urlPrefix = 'https://orcid.org/';
}
