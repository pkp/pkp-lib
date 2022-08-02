<?php

declare(strict_types=1);

/**
 * @file classes/observers/listeners/SubmissionFileDeletedListener.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileDeletedListener
 * @ingroup core
 *
 * @brief Listener fired when submission file's deleted
 */

namespace PKP\observers\listeners;

use Illuminate\Events\Dispatcher;
use PKP\Jobs\Submissions\RemoveSubmissionFileFromSearchIndexJob;

use PKP\observers\events\SubmissionFileDeleted;

class SubmissionFileDeletedListener
{
    /**
     * Maps methods with correspondent events to listen
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            SubmissionFileDeleted::class,
            self::class . '@handle'
        );
    }

    /**
     * Handle the listener call
     */
    public function handle(SubmissionFileDeleted $event)
    {
        dispatch(new RemoveSubmissionFileFromSearchIndexJob($event->submissionFile->getId()));
    }
}
