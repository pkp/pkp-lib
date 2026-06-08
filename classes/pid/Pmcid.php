<?php

/**
 * @file classes/pid/Pmcid.php
 *
 * Copyright (c) 2025-2026 Simon Fraser University
 * Copyright (c) 2025-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Pmcid
 *
 * @ingroup pid
 *
 * @brief Pmcid class
 *
 * @see https://www.ncbi.nlm.nih.gov/pmc/
 */

namespace PKP\pid;

class Pmcid extends BasePid
{
    /** @copydoc BasePid::regexes */
    public const regexes = [
        // pmcid:PMC1234567 https://www.ncbi.nlm.nih.gov/pmc/articles/PMC1234567
        '/(?:pmcid:\s*|https?:\/\/(?:www\.)?ncbi\.nlm\.nih\.gov\/pmc\/articles\/)PMC\d+/i'
    ];

    /** @copydoc BasePid::validationRegexes */
    public const validationRegexes = [
        '/^PMC\d+$/'
    ];

    /** @copydoc BasePid::prefix */
    public const prefix = 'pmcid:';

    /** @copydoc BasePid::urlPrefix */
    public const urlPrefix = 'https://www.ncbi.nlm.nih.gov/pmc/articles/';

    /** @copydoc BasePid::alternatePrefixes */
    public const alternatePrefixes = [
        'pmcid',
        'pmc:',
    ];
}
