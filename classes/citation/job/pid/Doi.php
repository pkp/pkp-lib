<?php

/**
 * @file classes/citation/job/pid/Doi.php
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
 */

namespace PKP\citation\job\pid;

class Doi extends BasePid
{
    /** @copydoc AbstractPid::regex */
    public const regex = '(10[.][0-9]{4,}[^\s"/<>]*/[^\s"<>]+)';

    /** @copydoc AbstractPid::prefix */
    public const prefix = 'https://doi.org';

    /** @copydoc AbstractPid::prefixInCorrect */
    public const prefixInCorrect = [
        'doi:',
        'dx.doi.org'
    ];
}
