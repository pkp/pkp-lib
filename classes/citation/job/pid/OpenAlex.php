<?php

/**
 * @file classes/citation/job/pid/OpenAlex.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OpenAlex
 *
 * @ingroup citation
 *
 * @brief OpenAlex class
 */

namespace PKP\citation\job\pid;

class OpenAlex extends BasePid
{
    /** @copydoc AbstractPid::regex */
    public const prefix = 'https://openalex.org';

    /** @copydoc AbstractPid::prefixInCorrect */
    public const prefixInCorrect = [
        'openalex:',
        'openalex.org/works',
        'www.openalex.org/works'
    ];
}
