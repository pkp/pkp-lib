<?php

/**
 * @file classes/mail/mailables/DecisionReviewerUnassignedNotifyReviewer.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DecisionReviewerUnassignedNotifyReviewer
 *
 * @brief Email sent to the author(s) when the following decision is made:
 *   Decision::BACK_TO_PREVIOUS_EXTERNAL_REVIEW_ROUND
 *   Decision::BACK_TO_PREVIOUS_INTERNAL_REVIEW_ROUND
 *   Decision::BACK_TO_SUBMISSION_FROM_EXTERNAL_REVIEW
 *   Decision::BACK_TO_SUBMISSION_FROM_INTERNAL_REVIEW
 *   Decision::BACK_TO_INTERNAL_REVIEW_FROM_EXTERNAL_REVIEW
 */

namespace PKP\mail\mailables;

use APP\decision\Decision;
use APP\submission\Submission;
use PKP\context\Context;

class DecisionReviewerUnassignedNotifyReviewer extends DecisionNotifyReviewer
{
    protected static ?string $name = 'mailable.reviewerUnassign.name';
    protected static ?string $description = 'mailable.reviewerUnassign.description';
    protected static ?string $emailTemplateKey = 'REVIEW_CANCEL';

    public function __construct(Context $context, Submission $submission, Decision $decision)
    {
        parent::__construct($context, $submission, $decision);
    }

    /**
     * Get a description of the decision to use as an email variable
     */
    protected function getDecisionDescription(?string $locale = null): string
    {
        return __('mailable.decision.notifyReviewer.variable.decisionDescription.unassigned', [], $locale);
    }
}
