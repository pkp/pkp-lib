<?php

/**
 * @file classes/mail/mailables/DecisionBackToInternalFromExternalReviewNotifyReviewer.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DecisionBackToInternalFromExternalReviewNotifyReviewer
 *
 * @brief Email sent to the author(s) when the following decision is made:
 *   SUBMISSION_EDITOR_DECISION_BACK_TO_INTERNAL_REVIEW_FROM_EXTERNAL_REVIEW
 */

namespace PKP\mail\mailables;

use APP\decision\Decision;
use APP\submission\Submission;
use PKP\context\Context;

class DecisionBackToInternalFromExternalReviewNotifyReviewer extends DecisionNotifyReviewer
{
    protected static ?string $name = 'mailable.decision.backToInternalReviewFromExternalReview.notifyReviewer.name';
    protected static ?string $description = 'mailable.decision.backToInternalReviewFromExternalReview.notifyReviewer.description';
    protected static ?string $emailTemplateKey = 'EDITOR_DECISION_BACK_TO_INTERNAL_REVIEW_FROM_EXTERNAL_REVIEW_NOTIFY_REVIEWER';

    public function __construct(Context $context, Submission $submission, Decision $decision)
    {
        parent::__construct($context, $submission, $decision);
    }

    /**
     * Get a description of the decision to use as an email variable
     */
    protected function getDecisionDescription(?string $locale = null): string
    {
        return __('mailable.decision.notifyReviewer.variable.decisionDescription.backToInternalReviewFromExternalReview', [], $locale);
    }
}
