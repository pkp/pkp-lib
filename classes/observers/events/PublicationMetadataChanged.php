<?php

declare(strict_types=1);

/**
 * @file classes/observers/events/PublicationMetadataChanged.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationMetadataChanged
 *
 * @ingroup core
 *
 * @brief Event fired when a publication's metadata changed
 */

namespace PKP\observers\events;

use APP\publication\Publication;
use Illuminate\Foundation\Events\Dispatchable;

class PublicationMetadataChanged
{
    use Dispatchable;

    public function __construct(public Publication $publication)
    {
    }
}
