<?php

/**
 * @file classes/citation/pid/Arxiv.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Arxiv
 *
 * @ingroup citation
 *
 * @brief Arxiv class
 */

namespace PKP\citation\pid;

class Arxiv extends BasePid
{
    /** @copydoc AbstractPid::regex */
    public const regexes = [
        // arxiv:2025.12345v2 https://arxiv.org/abs/2025.12345
        '/(?:arxiv:\s*|https?:\/\/arxiv.org\/(?:abs|pdf)\/)(?:\d+.\d+|[a-z-]+.\d+)/i'
    ];

    /** @copydoc AbstractPid::prefix */
    public const prefix = 'arxiv:';

    /** @copydoc AbstractPid::urlPrefix */
    public const urlPrefix = 'https://arxiv.org/abs/';

    /** @copydoc AbstractPid::alternatePrefixes */
    public const alternatePrefixes = [
        'arxiv',
        'https://arxiv.org/pdf/'
    ];
}
