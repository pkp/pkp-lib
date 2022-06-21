<?php

/**
 * @file classes/mail/mailables/DecisionBackToSubmissionNotifyReviewer.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DecisionBackToSubmissionNotifyReviewer
 *
 * @brief Email sent to the author(s) when the following decision is made:
 *   SUBMISSION_EDITOR_DECISION_BACK_TO_SUBMISSION_FROM_REVIEW
 */

namespace PKP\mail\mailables;

use APP\decision\Decision;
use APP\submission\Submission;
use PKP\context\Context;

class DecisionBackToSubmissionNotifyReviewer extends DecisionNotifyReviewer
{
    protected static ?string $name = 'mailable.decision.backToSubmission.notifyReviewer.name';
    protected static ?string $description = 'mailable.decision.backToSubmission.notifyReviewer.description';
    protected static ?string $emailTemplateKey = 'EDITOR_DECISION_BACK_TO_SUBMISSION_NOTIFY_REVIEWER';

    public function __construct(Context $context, Submission $submission, Decision $decision)
    {
        parent::__construct($context, $submission, $decision);
    }

    /**
     * Get a description of the decision to use as an email variable
     */
    protected function getDecisionDescription(?string $locale = null): string
    {
        $description = parent::getDecisionDescription($locale);

        if (!empty($description)) {
            return $description;
        }

        $decisionDescriptionMapping = [
            'BACK_TO_SUBMISSION_FROM_EXTERNAL_REVIEW' => __('mailable.decision.notifyReviewer.variable.decisionDescription.backToSubmissionFromExternalReview', [], $locale),
            'BACK_TO_SUBMISSION_FROM_INTERNAL_REVIEW' => __('mailable.decision.notifyReviewer.variable.decisionDescription.backToSubmissionFromInternalReview', [], $locale),
        ];

        $class = '\APP\decision\Decision';

        foreach ($decisionDescriptionMapping as $type => $desc) {
            if (!defined("{$class}::{$type}")) {
                continue;
            }

            if ($this->decision->getData('decision') == constant("{$class}::{$type}")) {
                return $desc;
            }
        }

        return '';
    }
}
