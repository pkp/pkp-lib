<?php

declare(strict_types=1);

/**
 * @file classes/observers/listeners/AssignDOIs.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AssignDOIs
 *
 * @ingroup core
 *
 * @brief Assign DOIs automatically depending on the DOI creation time: when a submission is
 *  submitted or moved to the appropriate stage, or a publication is published or versioned.
 *  Remove them when a submission is declined.
 */

namespace PKP\observers\listeners;

use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Events\Dispatcher;
use PKP\context\Context;
use PKP\decision\types\RevertDecline;
use PKP\decision\types\RevertInitialDecline;
use PKP\doi\Doi;
use PKP\observers\events\DecisionAdded;
use PKP\observers\events\PublicationPublished;
use PKP\observers\events\PublicationVersioned;
use PKP\observers\events\SubmissionSubmitted;

class AssignDOIs
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
        $events->listen(
            SubmissionSubmitted::class,
            self::class . '@handleSubmitted'
        );
        $events->listen(
            PublicationPublished::class,
            self::class . '@handlePublished'
        );
        $events->listen(
            PublicationVersioned::class,
            self::class . '@handleVersioned'
        );
    }

    /**
     * Allows DOI creation upon reaching copy-editing or production workflow stage,
     * and handles declining submissions whose DOIs were assigned on item creation
     */
    public function handle(DecisionAdded $event)
    {
        $context = $event->context;
        $doiCreationTime = $context->getData(Context::SETTING_DOI_CREATION_TIME);
        $workflowStageId = $event->decisionType->getNewStageId($event->submission, $event->decision->getData('reviewRoundId'));

        if (
            $doiCreationTime === Repo::doi()::CREATION_TIME_COPYEDIT
            && in_array($workflowStageId, [WORKFLOW_STAGE_ID_EDITING, WORKFLOW_STAGE_ID_PRODUCTION])
        ) {
            Repo::submission()->createDois($event->submission);
        }

        if (Repo::doi()->assignOnItemCreation($context)) {
            $this->handleDecline($event);
        }
    }

    /**
     * Allows DOI creation as soon as a submission is submitted
     */
    public function handleSubmitted(SubmissionSubmitted $event): void
    {
        if (Repo::doi()->assignOnItemCreation($event->context)) {
            Repo::submission()->createDois($event->submission);
        }
    }

    /**
     * Allows DOI creation upon publication, unless DOIs are never assigned automatically
     */
    public function handlePublished(PublicationPublished $event): void
    {
        if (
            $event->context->areDoisEnabled()
            && $event->context->getData(Context::SETTING_DOI_CREATION_TIME) !== Repo::doi()::CREATION_TIME_NEVER
        ) {
            Repo::publication()->createDois($event->publication);
        }
    }

    /**
     * Allows DOI creation for a new version, if the DOIs would otherwise only be assigned on its publication
     */
    public function handleVersioned(PublicationVersioned $event): void
    {
        if (
            Repo::doi()->assignOnItemCreation($event->context)
            || Repo::doi()->assignOnVersionCreation($event->context, $event->submission)
        ) {
            Repo::publication()->createDois($event->publication);
        }
    }

    /**
     * Remove unregistered DOIs when a submission is declined and re-create them when the decline is reverted
     */
    protected function handleDecline(DecisionAdded $event): void
    {
        $submission = $event->submission;
        // The event's submission already has the status set by the decision
        if ($event->decisionType->getNewStatus() === Submission::STATUS_DECLINED) {
            // DOIs of a published version are publicly visible, so keep all of them
            if (!empty($submission->getPublishedPublications())) {
                return;
            }

            foreach (Repo::doi()->getDoisForSubmission($submission->getId()) as $doiId) {
                $doi = Repo::doi()->get($doiId);
                // Deposited DOIs cannot be withdrawn
                if ($doi?->getStatus() === Doi::STATUS_UNREGISTERED) {
                    Repo::doi()->delete($doi);
                }
            }
        } elseif ($event->decisionType instanceof RevertInitialDecline || $event->decisionType instanceof RevertDecline) {
            Repo::submission()->createDois($submission);
        }
    }
}
