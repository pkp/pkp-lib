<?php

/**
 * @file classes/pid/Arxiv.php
 *
 * Copyright (c) 2025-2026 Simon Fraser University
 * Copyright (c) 2025-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Arxiv
 *
 * @ingroup pid
 *
 * @brief Arxiv class
 */

namespace PKP\pid;

class Arxiv extends BasePid
{
    /** @copydoc BasePid::regexes */
    public const regexes = [
        // arxiv:2025.12345v2 https://arxiv.org/abs/2025.12345
        // The version is matched in lower case only, as arXiv writes it.
        '/(?:arxiv:\s*|https?:\/\/arxiv\.org\/(?:abs|pdf)\/)(?:\d+\.\d+|[a-z.-]+\/\d+)(?-i:v\d+)?/i'
    ];

    /** @copydoc BasePid::validationRegexes */
    public const validationRegexes = [
        '/^(?:\d+\.\d+|[a-z.-]+\/\d+)(?-i:v\d+)?$/i'
    ];

    /** @copydoc BasePid::prefix */
    public const prefix = 'arxiv:';

    /** @copydoc BasePid::urlPrefix */
    public const urlPrefix = 'https://arxiv.org/abs/';

    /** @copydoc BasePid::alternatePrefixes */
    public const alternatePrefixes = [
        'arxiv',
        'https://arxiv.org/pdf/'
    ];
}
