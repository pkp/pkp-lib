<?php

declare(strict_types=1);

/**
 * @file classes/observers/events/PublicationVersioned.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationVersioned
 *
 * @ingroup observers_events
 *
 * @brief Event fired when a new version of a publication has been created, including its copied objects
 */

namespace PKP\observers\events;

use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Foundation\Events\Dispatchable;
use PKP\context\Context;

class PublicationVersioned
{
    use Dispatchable;

    /**
     * @param Publication $publication The new version
     * @param Publication $oldPublication The publication it was copied from
     */
    public function __construct(
        public Publication $publication,
        public Publication $oldPublication,
        public Submission $submission,
        public Context $context
    ) {
    }
}
