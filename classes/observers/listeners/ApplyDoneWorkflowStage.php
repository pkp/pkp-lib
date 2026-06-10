<?php

/**
 * @file classes/observers/listeners/ApplyDoneWorkflowStage.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ApplyDoneWorkflowStage
 *
 * @ingroup observers_listeners
 *
 * @brief Applies move to or return from done workflow stages upon publication status change
 */

namespace PKP\observers\listeners;

use APP\core\Application;
use APP\decision\Decision;
use APP\facades\Repo;
use APP\publication\enums\VersionStage;
use Illuminate\Events\Dispatcher;
use PKP\observers\events\PublicationPublished;
use PKP\observers\events\PublicationUnpublished;
use PKP\publication\PKPPublication;

class ApplyDoneWorkflowStage
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            [PublicationPublished::class, PublicationUnpublished::class],
            ApplyDoneWorkflowStage::class
        );
    }

    public function handle(PublicationPublished|PublicationUnpublished $event): void
    {
        $submission = $event->submission;

        $publishedVoRCount = Repo::publication()->getCollector()
            ->filterBySubmissionIds([$submission->getId()])
            ->filterByVersionStage(VersionStage::finalVersionStage()->value)
            ->filterByStatus([PKPPublication::STATUS_PUBLISHED])
            ->getCount();

        if ($publishedVoRCount >= 1 && $submission->getData('stageId') !== WORKFLOW_STAGE_ID_DONE) {
            $fromStageId = $submission->getData('stageId');

            $moveToDone = Repo::decision()->newDataObject([
                'decision' => Decision::MOVE_TO_DONE,
                'submissionId' => $submission->getId(),
                'editorId' => Application::get()->getRequest()->getUser()?->getId()
                    ?? Repo::submission()->resolveSystemEditorId($submission),
                'stageId' => $fromStageId,
            ]);
            Repo::decision()->add($moveToDone);

        } elseif ($publishedVoRCount === 0 && $submission->getData('stageId') === WORKFLOW_STAGE_ID_DONE) {
            $returnToWorkflow = Repo::decision()->newDataObject([
                'decision' => Decision::RETURN_TO_WORKFLOW,
                'submissionId' => $submission->getId(),
                'editorId' => Application::get()->getRequest()->getUser()?->getId()
                    ?? Repo::submission()->resolveSystemEditorId($submission),
                'stageId' => WORKFLOW_STAGE_ID_DONE,
            ]);

            Repo::decision()->add($returnToWorkflow);
        }

    }
}
