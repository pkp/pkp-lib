<?php

/**
 * @file classes/citation/pid/Url.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Url
 *
 * @ingroup citation
 *
 * @brief Url class
 */

namespace PKP\citation\pid;

class Url extends BasePid
{
    /** @copydoc AbstractPid::regex */
    public const regexes = [
        '#https?://(www\.)?[-a-zA-Z0-9@:%._\+~\#=]{2,256}\.[a-z]{2,4}\b([-a-zA-Z0-9@:%_\+.~\#?&//=]*)#'
    ];
}
