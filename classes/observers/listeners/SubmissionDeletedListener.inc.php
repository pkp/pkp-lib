<?php

declare(strict_types=1);

/**
 * @file classes/observers/listeners/SubmissionDeletedListener.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionDeletedListener
 * @ingroup core
 *
 * @brief Listener fired when submission's deleted
 */

namespace PKP\observers\listeners;

use PKP\Jobs\Submissions\RemoveSubmissionFromSearchIndexJob;
use PKP\observers\events\SubmissionDeleted;

class SubmissionDeletedListener
{
    /**
     * Handle the listener call
     *
     *
     */
    public function handle(SubmissionDeleted $event)
    {
        dispatch(new RemoveSubmissionFromSearchIndexJob($event->submission->getId()));
    }
}
