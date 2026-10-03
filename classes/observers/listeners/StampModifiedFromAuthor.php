<?php

declare(strict_types=1);

/**
 * @file classes/observers/listeners/StampModifiedFromAuthor.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StampModifiedFromAuthor
 *
 * @ingroup core
 *
 * @brief Continues the last_modified cascade from an author to its publication
 */

namespace PKP\observers\listeners;

use PKP\jobs\metadata\StampModifiedJob;
use PKP\observers\events\AuthorMetadataChanged;

class StampModifiedFromAuthor
{
    public function handle(AuthorMetadataChanged $event): void
    {
        dispatch(new StampModifiedJob(
            StampModifiedJob::FROM_AUTHOR,
            (int) $event->author->getData('publicationId')
        ));
    }
}
