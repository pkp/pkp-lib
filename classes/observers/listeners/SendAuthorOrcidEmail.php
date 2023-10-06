<?php

/**
 * @file classes/observers/listeners/SendAuthorOrcidEmail.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SendAuthorOrcidEmail
 *
 * @ingroup observers_listeners
 *
 * @brief Dispatches job to send ORCID authorization request to authors on submission post acceptance
 */

namespace PKP\observers\listeners;

use APP\decision\Decision;
use APP\facades\Repo;
use Carbon\Carbon;
use Illuminate\Events\Dispatcher;
use PKP\jobs\orcid\SendAuthorMail;
use PKP\observers\events\DecisionAdded;
use PKP\orcid\OrcidManager;

class SendAuthorOrcidEmail
{
    /**
     * Maps methods with corresponding events to listen to
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            DecisionAdded::class,
            self::class . '@handle'
        );
    }

    public function handle(DecisionAdded $decisionEvent): void
    {
        $context = $decisionEvent->context;

        if (!OrcidManager::isEnabled($context)) {
            return;
        }

        $allowedDecisionTypes = [
            Decision::ACCEPT,
            Decision::SKIP_EXTERNAL_REVIEW,
        ];

        if (in_array($decisionEvent->decisionType->getDecision(), $allowedDecisionTypes) &&
            $context->getData(OrcidManager::SEND_MAIL_TO_AUTHORS_ON_PUBLICATION)) {
            $submission = $decisionEvent->submission;
            $publication = $submission->getCurrentPublication();

            if (isset($publication)) {
                $authors = Repo::author()->getCollector()
                    ->filterByPublicationIds([$publication->getId()])
                    ->getMany();

                foreach ($authors as $author) {
                    $orcidAccessExpiresOn = Carbon::parse($author->getData('orcidAccessExpiresOn'));
                    if ($author->getData('orcidAccessToken') == null || $orcidAccessExpiresOn->isPast()) {
                        dispatch(new SendAuthorMail($author, $context, true));
                    }
                }
            }
        }
    }
}
