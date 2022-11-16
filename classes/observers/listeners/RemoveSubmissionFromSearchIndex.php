<?php

declare(strict_types=1);

/**
 * @file classes/observers/listeners/RemoveSubmissionFromSearchIndex.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RemoveSubmissionFromSearchIndex
 * @ingroup core
 *
 * @brief Remove a submission from the search index when it is deleted
 */

namespace PKP\observers\listeners;

use Illuminate\Events\Dispatcher;
use PKP\Jobs\Submissions\RemoveSubmissionFromSearchIndexJob;

use PKP\observers\events\SubmissionDeleted;

class RemoveSubmissionFromSearchIndex
{
    /**
     * Maps methods with correspondent events to listen
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            SubmissionDeleted::class,
            self::class . '@handle'
        );
    }

    /**
     * Handle the listener call
     */
    public function handle(SubmissionDeleted $event)
    {
        dispatch(new RemoveSubmissionFromSearchIndexJob($event->submissionId));
    }
}
