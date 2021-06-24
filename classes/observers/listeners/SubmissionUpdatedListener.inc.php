<?php

declare(strict_types=1);

/**
 * @file classes/observers/listeners/SubmissionUpdatedListener.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionUpdatedListener
 * @ingroup core
 *
 * @brief Listener fired when submission's updated
 */

namespace PKP\observers\listeners;

use PKP\Jobs\Submissions\UpdateSubmissionSearchJob;
use PKP\observers\events\PublishedEvent;

class SubmissionUpdatedListener
{
    /**
     * Handle the listener call
     *
     *
     */
    public function handle(PublishedEvent $event)
    {
        dispatch(new UpdateSubmissionSearchJob($event->submission->getId()));
    }
}
