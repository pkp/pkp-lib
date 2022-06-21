<?php

/**
 * @file classes/mail/mailables/DecisionBackToPreviousExternalReviewRoundNotifyReviewer.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DecisionBackToPreviousExternalReviewRoundNotifyReviewer
 *
 * @brief Email sent to the author(s) when the following decision is made:
 *   SUBMISSION_EDITOR_DECISION_BACK_TO_PREVIOUR_EXTERNAL_REVIEW_ROUND
 */

namespace PKP\mail\mailables;

use APP\decision\Decision;
use APP\submission\Submission;
use PKP\context\Context;

class DecisionBackToPreviousExternalReviewRoundNotifyReviewer extends DecisionNotifyReviewer
{
    protected static ?string $name = 'mailable.decision.backToPreviousExternalReviewRound.notifyReviewer.name';
    protected static ?string $description = 'mailable.decision.backToPreviousExternalReviewRound.notifyReviewer.description';
    protected static ?string $emailTemplateKey = 'EDITOR_DECISION_BACK_TO_PREVIOUS_EXTERNAL_REVIEW_ROUND_NOTIFY_REVIEWER';

    public function __construct(Context $context, Submission $submission, Decision $decision)
    {
        parent::__construct($context, $submission, $decision);
    }
}
