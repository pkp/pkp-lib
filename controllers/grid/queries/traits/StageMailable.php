<?php

/**
 * @file mail/traits/StageMailable.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StageMailable
 * @ingroup mail_traits
 *
 * @brief Mailable trait to associate Workflow Stage with specific Discussion email
 */

namespace PKP\controllers\grid\queries\traits;

use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\mailables\DiscussionCopyediting;
use PKP\mail\mailables\DiscussionProduction;
use PKP\mail\mailables\DiscussionReview;
use PKP\mail\mailables\DiscussionSubmission;

trait StageMailable
{
    abstract public function getStageId();

    /**
     * @return Mailable which corresponds to the given workflow stage
     */
    protected function getStageMailable(Context $context, Submission $submission, string $subject = '', string $message = ''): Mailable
    {
        $map = [
            WORKFLOW_STAGE_ID_SUBMISSION => DiscussionSubmission::class,
            WORKFLOW_STAGE_ID_INTERNAL_REVIEW => DiscussionReview::class,
            WORKFLOW_STAGE_ID_EXTERNAL_REVIEW => DiscussionReview::class,
            WORKFLOW_STAGE_ID_EDITING => DiscussionCopyediting::class,
            WORKFLOW_STAGE_ID_PRODUCTION => DiscussionProduction::class,
        ];
        $mailableClassName = $map[$this->getStageId()];
        return new $mailableClassName($context, $submission, $subject, $message);
    }
}
