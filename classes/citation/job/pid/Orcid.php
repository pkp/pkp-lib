<?php

/**
 * @file classes/citation/job/pid/Orcid.php
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

namespace PKP\citation\job\pid;

class Orcid extends BasePid
{
    /** @copydoc AbstractPid::regex */
    public const prefix = 'https://orcid.org';

    /** @copydoc AbstractPid::prefixInCorrect */
    public const prefixInCorrect = [
        'orcid:',
        'orcid_id:',
        'orcidId:'
    ];
}
