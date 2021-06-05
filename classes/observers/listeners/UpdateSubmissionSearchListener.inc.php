<?php

declare(strict_types=1);

/**
 * @file classes/observers/listeners/UpdateSubmissionSearchListener.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UpdateSubmissionSearchListener
 * @ingroup core
 *
 * @brief Listener fired when submission's updated
 */

namespace PKP\observers\listeners;

use PKP\Jobs\Submissions\UpdateSubmissionSearchJob;
use PKP\observers\events\PublishedEvent;

class UpdateSubmissionSearchListener
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
