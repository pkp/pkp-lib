<?php

declare(strict_types=1);

/**
 * @file classes/observers/listeners/StampModifiedFromCitation.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StampModifiedFromCitation
 *
 * @ingroup core
 *
 * @brief Continues the last_modified cascade from a citation to its publication
 */

namespace PKP\observers\listeners;

use PKP\jobs\metadata\StampModifiedJob;
use PKP\observers\events\CitationMetadataChanged;

class StampModifiedFromCitation
{
    public function handle(CitationMetadataChanged $event): void
    {
        dispatch(new StampModifiedJob(
            StampModifiedJob::FROM_CITATION,
            $event->publicationId
        ));
    }
}
