<?php
/**
 * @file classes/decision/types/Decline.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Decline
 *
 * @brief A decision to decline a submission in the initial submission stage.
 */

namespace APP\decision\types;

use APP\submission\Submission;
use PKP\components\fileAttachers\Library;
use PKP\components\fileAttachers\Upload;
use PKP\context\Context;
use PKP\decision\types\InitialDecline;

class Decline extends InitialDecline
{
    /** @copydoc DecisionType::getStageId() */
    public function getStageId(): int
    {
        return WORKFLOW_STAGE_ID_PRODUCTION;
    }

    /**
     * Get the file attacher components supported for emails in this decision
     */
    protected function getFileAttachers(Submission $submission, Context $context): array
    {
        return [
            new Upload(
                $context,
                __('common.upload.addFile'),
                __('common.upload.addFile.description'),
                __('common.upload.addFile')
            ),
            new Library(
                $context,
                $submission
            )
        ];
    }
}
