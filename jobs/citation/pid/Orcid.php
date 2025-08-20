<?php

/**
 * @file jobs/citation/pid/Orcid.php
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

namespace PKP\jobs\citation\pid;

class Orcid extends BasePid
{
    /** @copydoc AbstractPid::regex */
    public const string prefix = 'https://orcid.org';

    /** @copydoc AbstractPid::prefixInCorrect */
    public const array prefixInCorrect = [
        'orcid:',
        'orcid_id:',
        'orcidId:'
    ];
}
