<?php

declare(strict_types=1);

/**
 * @file classes/observers/listeners/VersionDois.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class VersionDois
 *
 * @ingroup observers_listeners
 *
 * @brief Listener fired when publication's published
 */

namespace PKP\observers\listeners;

use APP\facades\Repo;
use Illuminate\Events\Dispatcher;
use PKP\context\Context;
use PKP\observers\events\PublicationPublished;

class VersionDois
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            PublicationPublished::class,
            self::class . '@handlePublishedEvent'
        );
    }

    /**
     * Handle DOI assignment at the publication stage and versioning
     */
    public function handlePublishedEvent(PublicationPublished $event): void
    {
        $submission = $event->submission;
        $context = $event->context;

        $doisEnabled = $context->getData(Context::SETTING_ENABLE_DOIS);

        if (!$doisEnabled) {
            return;
        }

        $_failureResults = Repo::submission()->createDois($submission);
    }
}
