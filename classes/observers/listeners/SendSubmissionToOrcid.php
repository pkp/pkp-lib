<?php

/**
 * @file classes/observers/listeners/SendSubmissionToOrcid.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SendSubmissionToOrcid
 *
 * @ingroup observers_listeners
 *
 * @brief Dispatches job to send submission metadata to authorized ORCID profile
 */

namespace PKP\observers\listeners;

use Illuminate\Events\Dispatcher;
use PKP\observers\events\PublicationPublished;
use PKP\orcid\OrcidManager;
use PKP\submission\PKPSubmission;

class SendSubmissionToOrcid
{
    /**
     * Maps methods with corresponding events to listen to
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            PublicationPublished::class,
            self::class . '@handle'
        );
    }

    public function handle(PublicationPublished $publishedEvent): void
    {
        $context = $publishedEvent->context;
        if (!OrcidManager::isEnabled($context)) {
            return;
        }

        $publicationStatus = $publishedEvent->publication->getData('status');
        if ($publicationStatus === PKPSubmission::STATUS_PUBLISHED ||
                $publicationStatus === PKPSubmission::STATUS_SCHEDULED) {
            (new \APP\orcid\actions\SendSubmissionToOrcid($publishedEvent->publication, $publishedEvent->context))->execute();
        }
    }
}
