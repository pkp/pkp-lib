<?php

declare(strict_types=1);

/**
 * @file classes/observers/listeners/RemoveSubmissionFileFromSearchIndex.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RemoveSubmissionFileFromSearchIndex
 *
 * @ingroup core
 *
 * @brief Remove a submission file from the search index when it is deleted.
 */

namespace PKP\observers\listeners;

use Illuminate\Events\Dispatcher;
use PKP\jobs\submissions\RemoveSubmissionFileFromSearchIndexJob;
use PKP\observers\events\SubmissionFileDeleted;

class RemoveSubmissionFileFromSearchIndex
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
        dispatch(
            new RemoveSubmissionFileFromSearchIndexJob(
                $event->submissionId,
                $event->submissionFileId
            )
        );
    }
}
