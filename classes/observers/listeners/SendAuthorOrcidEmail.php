<?php

namespace PKP\observers\listeners;

use APP\decision\Decision;
use APP\facades\Repo;
use APP\orcid\actions\SendAuthorMail;
use APP\orcid\OrcidManager;
use Carbon\Carbon;
use Illuminate\Events\Dispatcher;
use PKP\observers\events\DecisionAdded;

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

        if ($decisionEvent->decisionType->getDecision() == Decision::ACCEPT &&
            $context->getData(OrcidManager::SEND_MAIL_TO_AUTHORS_ON_PUBLICATION)) {
            $submission = $decisionEvent->submission;
            $publication = $submission->getCurrentPublication();

            if (isset($publication)) {
                $authors = Repo::author()->getCollector()
                    ->filterByPublicationIds([$submission->getCurrentPublication()->getId()])
                    ->getMany();

                foreach ($authors as $author) {
                    $orcidAccessExpiresOn = Carbon::parse($author->getData('orcidAccessExpiresOn'));
                    if ($author->getData('orcidAccessToken') == null || $orcidAccessExpiresOn->isPast()) {
                        (new SendAuthorMail($author, $context, true))->execute();
                    }
                }
            }
        }
    }
}
