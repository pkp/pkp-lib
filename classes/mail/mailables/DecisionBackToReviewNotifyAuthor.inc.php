<?php

/**
 * @file classes/mail/mailables/DecisionBackToReviewNotifyAuthor.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DecisionBackToReviewNotifyAuthor
 *
 * @brief Email sent to the author(s) when a SUBMISSION_EDITOR_DECISION_BACK_TO_REVIEW
 *  decision is made.
 */

namespace PKP\mail\mailables;

use APP\decision\Decision;
use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;

class DecisionBackToReviewNotifyAuthor extends Mailable
{
    use Recipient;
    use Sender;

    protected static ?string $name = 'mailable.decision.backToReview.notifyAuthor.name';
    protected static ?string $description = 'mailable.decision.backToReview.notifyAuthor.description';
    protected static ?string $emailTemplateKey = 'EDITOR_DECISION_BACK_TO_REVIEW';
    protected static bool $supportsTemplates = true;
    protected static array $groupIds = [self::GROUP_COPYEDITING];

    public function __construct(Context $context, Submission $submission, Decision $decision)
    {
        parent::__construct(func_get_args());
    }
}
