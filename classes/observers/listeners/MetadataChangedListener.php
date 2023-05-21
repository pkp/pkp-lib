<?php

declare(strict_types=1);

/**
 * @file classes/observers/listeners/MetadataChangedListener.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MetadataChangedListener
 *
 * @ingroup core
 *
 * @brief Listener fired when submission metadata's changed
 */

namespace PKP\observers\listeners;

use Illuminate\Events\Dispatcher;
use PKP\jobs\metadata\MetadataChangedJob;
use PKP\observers\events\MetadataChanged;

class MetadataChangedListener
{
    /**
     * Maps methods with correspondent events to listen
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            MetadataChanged::class,
            self::class . '@handle'
        );
    }

    /**
     * Handle the listener call
     */
    public function handle(MetadataChanged $event)
    {
        dispatch(new MetadataChangedJob($event->submission->getId()));
    }
}
