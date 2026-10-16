<?php

declare(strict_types=1);

/**
 * @file classes/observers/events/CitationMetadataChanged.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CitationMetadataChanged
 *
 * @ingroup core
 *
 * @brief Event fired when a citation's metadata changed
 */

namespace PKP\observers\events;

use Illuminate\Foundation\Events\Dispatchable;

class CitationMetadataChanged
{
    use Dispatchable;

    public function __construct(public int $publicationId)
    {
    }
}
