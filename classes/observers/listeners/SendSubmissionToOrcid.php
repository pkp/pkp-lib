<?php

namespace PKP\observers\listeners;

use APP\facades\Repo;
use APP\jobs\orcid\DepositOrcidSubmission;
use APP\orcid\OrcidManager;
use APP\orcid\OrcidWork;
use APP\publication\Publication;
use Carbon\Carbon;
use Illuminate\Events\Dispatcher;
use PKP\context\Context;
use PKP\observers\events\PublicationPublished;
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
            $this->sendSubmissionToOrcid($publishedEvent->publication, $publishedEvent->context);
        }
    }

    private function sendSubmissionToOrcid(Publication $publication, Context $context): void
    {
        if (!OrcidManager::isMemberApiEnabled($context)) {
            // Sending to ORCID only works with the member API
            return;
        }

        $issueId = $publication->getData('issueId');
        if (isset($issueId)) {
            $issue = Repo::issue()->get($issueId);
        }

        $authors = Repo::author()
            ->getCollector()
            ->filterByPublicationIds([$publication->getId()])
            ->getMany();

        // Collect valid ORCID ids and access tokens from submission contributors
        $authorsWIthOrcid = [];
        foreach ($authors as $author) {
            if ($author->getOrcid() && $author->getData('orcidAccessToken')) {
                $orcidAccessExpiresOn = Carbon::parse($author->getData('orcidAccessExpiresOn'));
                if ($orcidAccessExpiresOn->isFuture()) {
                    # Extract only the ORCID from the stored ORCID uri
                    $orcid = basename(parse_url($author->getOrcid(), PHP_URL_PATH));
                    $authorsWithOrcid[$orcid] = $author;
                } else {
                    // TODO: In sandbox mode, the `DepositOrcidSubmission` job is dispatched but is not actually run.
                    //      Is it okay to still remove the expired ORCID access tokens even if the job isn't being run?
                    //      This shouldn't be a problem as they're already expired, but even so...
                    OrcidManager::logError("Token expired on {$orcidAccessExpiresOn} for author " . $author->getId() . ', deleting orcidAccessToken!');
                    OrcidManager::removeOrcidAccessToken($author);
                }
            }
        }

        if (empty($authorsWithOrcid)) {
            OrcidManager::logInfo('No contributor with ORICD id or valid access token for submission ' . $publication->getData('submissionId'));
            return;
        }

        $orcidWork = new OrcidWork($publication, $context, $authors->toArray(), $issue ?? null);
        OrcidManager::logInfo('Request body (without put-code): ' . json_encode($orcidWork->toArray()));

        foreach ($authorsWithOrcid as $orcid => $author) {
            dispatch(new DepositOrcidSubmission($author, $context, $orcidWork->toArray(), $orcid));
        }

    }
}
