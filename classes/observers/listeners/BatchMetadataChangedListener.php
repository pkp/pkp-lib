<?php

declare(strict_types=1);

/**
 * @file classes/observers/listeners/BatchMetadataChangedListener.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BatchMetadataChangedListener
 * @ingroup core
 *
 * @brief Listener fired when submission metadata's changed on batch
 */

namespace PKP\observers\listeners;

use Illuminate\Events\Dispatcher;
use PKP\Jobs\Metadata\BatchMetadataChangedJob;

use PKP\observers\events\BatchMetadataChanged;

class BatchMetadataChangedListener
{
    /**
     * Maps methods with correspondent events to listen
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            BatchMetadataChanged::class,
            self::class . '@handle'
        );
    }

    /**
     * Handle the listener call
     */
    public function handle(BatchMetadataChanged $event)
    {
        dispatch(new BatchMetadataChangedJob($event->submissionIds));
    }
}
