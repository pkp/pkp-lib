<?php

declare(strict_types=1);

/**
 * @file classes/observers/listeners/StampModifiedFromPublication.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StampModifiedFromPublication
 *
 * @ingroup core
 *
 * @brief Continues the last_modified cascade from a publication to its submission
 */

namespace PKP\observers\listeners;

use PKP\jobs\metadata\StampModifiedJob;
use PKP\observers\events\PublicationMetadataChanged;

class StampModifiedFromPublication
{
    public function handle(PublicationMetadataChanged $event): void
    {
        dispatch(new StampModifiedJob(
            StampModifiedJob::FROM_PUBLICATION,
            (int) $event->publication->getData('submissionId')
        ));
    }
}
