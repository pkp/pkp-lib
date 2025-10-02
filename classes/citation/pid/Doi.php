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
    public const regex = '(10[.][0-9]{4,}[^\s"/<>]*/[^\s"<>]+)';

    /** @copydoc AbstractPid::prefix */
    public const prefix = 'https://doi.org';

    /** @copydoc AbstractPid::prefixInCorrect */
    public const prefixInCorrect = [
        'doi:',
        'dx.doi.org'
    ];
}
