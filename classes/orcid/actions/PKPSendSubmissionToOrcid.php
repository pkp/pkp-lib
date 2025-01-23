<?php

/**
 * @file classes/orcid/actions/PKPSendSubmissionToOrcid.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSendSubmissionToOrcid
 *
 * @brief Compile and trigger deposits of submissions to ORCID.
 */

namespace PKP\orcid\actions;

use APP\facades\Repo;
use APP\orcid\actions\SendReviewToOrcid;
use APP\publication\Publication;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use PKP\context\Context;
use PKP\jobs\orcid\DepositOrcidSubmission;
use PKP\orcid\OrcidManager;
use PKP\orcid\PKPOrcidWork;
use PKP\submission\reviewAssignment\ReviewAssignment;

abstract class PKPSendSubmissionToOrcid
{
    public function __construct(
        protected Publication $publication,
        protected Context $context,
    ) {
    }

    /**
     * Gather valid authors with ORCIDs and dispatch job to deposit ORCID submission for each.
     */
    public function execute(): void
    {
        if (!OrcidManager::isEnabled($this->context)) {
            return;
        }

        if (!OrcidManager::isMemberApiEnabled($this->context) || $this->canDepositSubmission() === false) {
            // Sending to ORCID only works with the member API
            // FIXME: OMP cannot deposit submissions currently. Check can be removed once added
            return;
        }

        $authors = Repo::author()
            ->getCollector()
            ->filterByPublicationIds([$this->publication->getId()])
            ->getMany();

        // Collect valid ORCID ids and access tokens from submission contributors
        $authorsWithOrcid = [];
        foreach ($authors as $author) {
            if ($author->getOrcid() && $author->getData('orcidAccessToken')) {
                $orcidAccessExpiresOn = Carbon::parse($author->getData('orcidAccessExpiresOn'));
                if ($orcidAccessExpiresOn->isFuture()) {
                    # Extract only the ORCID from the stored ORCID uri
                    $orcid = basename(parse_url($author->getOrcid(), PHP_URL_PATH));
                    $authorsWithOrcid[$orcid] = $author;
                } else {
                    OrcidManager::logError("Token expired on {$orcidAccessExpiresOn} for author " . $author->getId() . ', deleting orcidAccessToken!');
                    OrcidManager::removeOrcidAccessToken($author);
                }
            }
        }

        if (empty($authorsWithOrcid)) {
            OrcidManager::logInfo('No contributor with ORICD id or valid access token for submission ' . $this->publication->getData('submissionId'));
        } else {
            $orcidWork = $this->getOrcidWork($authors->toArray());
            OrcidManager::logInfo('Request body (without put-code): ' . json_encode($orcidWork->toArray()));

            if ($orcidWork === null) {
                return;
            }
            foreach ($authorsWithOrcid as $orcid => $author) {
                dispatch(new DepositOrcidSubmission($author, $this->context, $orcidWork->toArray(), $orcid));
            }
        }

        $this->depositReviewsForSubmission();
    }

    /**
     * Get app-specific ORCID work for sending to ORCID
     *
     */
    abstract protected function getOrcidWork(array $authors): ?PKPOrcidWork;

    /**
     * Whether the application can make deposits to ORCID.
     * Currently only possible for OJS and OPS.
     * FIXME: This method/check can be removed once functionality added to OMP.
     *
     */
    abstract protected function canDepositSubmission(): bool;


    /**
     * Deposit reviews for the submission to ORCID.
     */
    public function depositReviewsForSubmission(): void
    {
        $submissionId = $this->publication->getData('submissionId');
        OrcidManager::logInfo('Submitting reviews for submission ' . $submissionId);

        collect(Repo::reviewAssignment()->getCollector()
            ->filterByContextIds([$this->context->getId()])
            ->filterBySubmissionIds([$submissionId])
            ->getMany())
            ->each(fn (ReviewAssignment $review) => (new SendReviewToOrcid($review->getId()))->execute());
    }
}
