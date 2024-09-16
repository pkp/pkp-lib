<?php

/**
 * @file classes/orcid/actions/PKPSendReviewToOrcid.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSendReviewToOrcid
 *
 * @brief Trigger review submission to ORCID if supported by the application (currently only OJS).
 */

namespace PKP\orcid\actions;

use APP\submission\Submission;
use PKP\context\Context;
use PKP\submission\reviewAssignment\ReviewAssignment;

abstract class PKPSendReviewToOrcid
{
    public function __construct(
        protected int $reviewAssignmentId,
    ) {

    }

    /**
     * Should be overridden on an app level to dispatch appropriate job, e.g. PublishReviewerWorkToOrcid in OJS.
     */
    public function execute(): void
    {
        //
    }
}
