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

use Illuminate\Events\Dispatcher;
use PKP\Jobs\Submissions\UpdateSubmissionSearchJob;
use PKP\observers\events\PublishedEvent;
use PKP\observers\events\UnpublishedEvent;

class SubmissionUpdatedListener
{
    /**
     * Maps methods with correspondent events to listen
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            PublishedEvent::class,
            self::class . '@handlePublishedEvent'
        );

        $events->listen(
            UnpublishedEvent::class,
            self::class . '@handleUnpublished'
        );
    }

    public function handleUnpublished(UnpublishedEvent $event)
    {
        dispatch(new UpdateSubmissionSearchJob($event->submission->getId()));
    }

    public function handlePublishedEvent(PublishedEvent $event)
    {
        dispatch(new UpdateSubmissionSearchJob($event->submission->getId()));
    }
}
