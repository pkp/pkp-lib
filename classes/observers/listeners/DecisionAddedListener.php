<?php

declare(strict_types=1);

/**
 * @file classes/observers/listeners/DecisionAddedListener.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DecisionAddedListener
 * @ingroup core
 *
 * @brief Listener fired when an editorial decision is added
 */

namespace PKP\observers\listeners;

use APP\facades\Repo;
use Illuminate\Events\Dispatcher;
use PKP\context\Context;
use PKP\observers\events\DecisionAdded;

class DecisionAddedListener
{
    /**
     * Maps methods with corresponding events to listen to
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            DecisionAdded::class,
            self::class . '@handleAutomaticDoiCreation'
        );
    }

    /**
     * Allows DOI creation upon reaching copy-editing or production workflow stage
     */
    public function handleAutomaticDoiCreation(DecisionAdded $event)
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
    }
}
