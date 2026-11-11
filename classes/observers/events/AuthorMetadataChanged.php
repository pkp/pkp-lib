<?php

declare(strict_types=1);

/**
 * @file classes/observers/events/AuthorMetadataChanged.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorMetadataChanged
 *
 * @ingroup core
 *
 * @brief Event fired when an author's metadata changed
 */

namespace PKP\observers\events;

use APP\author\Author;
use Illuminate\Foundation\Events\Dispatchable;

class AuthorMetadataChanged
{
    use Dispatchable;

    public function __construct(public Author $author)
    {
    }
}
