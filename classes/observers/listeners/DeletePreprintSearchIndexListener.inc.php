<?php

declare(strict_types=1);

/**
 * @file classes/observers/listeners/DeletePreprintSearchIndexListener.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DeletePreprintSearchIndexListener
 * @ingroup core
 *
 * @brief Listener fired when monograph was deleted
 */

namespace APP\observers\listeners;

use APP\observers\events\DeletePreprintSearchIndex;

use Illuminate\Events\Dispatcher;
use PKP\Jobs\Submissions\RemoveSubmissionFromSearchIndexJob;

class DeletePreprintSearchIndexListener
{
    /**
     * Maps methods with correspondent events to listen
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            DeletePreprintSearchIndex::class,
            self::class . '@handle'
        );
    }

    /**
     * Handle the listener call
     */
    public function handle(DeletePreprintSearchIndex $event)
    {
        dispatch(new RemoveSubmissionFromSearchIndexJob($event->preprintId));
    }
}
