<?php
/**
 * @file classes/decision/types/RevertDecline.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief A decision to revert a declined submission and return it to the active queue.
 */

namespace APP\decision\types;

use APP\submission\Submission;
use Exception;
use PKP\components\fileAttachers\Library;
use PKP\components\fileAttachers\Upload;
use PKP\context\Context;
use PKP\decision\types\RevertInitialDecline;
use PKP\submission\reviewRound\ReviewRound;

class RevertDecline extends RevertInitialDecline
{
    /** @copydoc DecisionType::getStageId() */
    public function getStageId(): int
    {
        return WORKFLOW_STAGE_ID_PRODUCTION;
    }

    /**
     * Get the file attacher components supported for emails in this decision
     */
    protected function getFileAttachers(Submission $submission, Context $context, ?ReviewRound $reviewRound = null): array
    {
        // The $reviewRound argument is necessary because this extends PKP\decision\types\RevertDecline,
        // but it should never be used.
        if (!is_null($reviewRound)) {
            throw new Exception('Review round not supported in this decision type.');
        }

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
