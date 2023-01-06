<?php

declare(strict_types=1);

/**
 * @file classes/observers/listeners/UpdateSubmissionInSearchIndex.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UpdateSubmissionInSearchIndex
 * @ingroup core
 *
 * @brief Reindex a submission in the search index when a publication is published or unpublished
 */

namespace PKP\observers\listeners;

use Illuminate\Events\Dispatcher;
use PKP\jobs\submissions\UpdateSubmissionSearchJob;
use PKP\observers\events\PublicationPublished;
use PKP\observers\events\PublicationUnpublished;

class UpdateSubmissionInSearchIndex
{
    /**
     * Maps methods with correspondent events to listen
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            PublicationPublished::class,
            self::class . '@handlePublicationPublished'
        );

        $events->listen(
            PublicationUnpublished::class,
            self::class . '@handleUnpublished'
        );
    }

    public function handleUnpublished(PublicationUnpublished $event)
    {
        dispatch(new UpdateSubmissionSearchJob($event->submission->getId()));
    }

    public function handlePublicationPublished(PublicationPublished $event)
    {
        dispatch(new UpdateSubmissionSearchJob($event->submission->getId()));
    }
}
