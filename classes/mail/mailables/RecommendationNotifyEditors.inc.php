<?php

/**
 * @file classes/mail/mailables/RecommendationNotifyEditors.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RecommendationNotifyEditors
 *
 * @brief Message sent to deciding editors when any SUBMISSION_EDITOR_RECOMMEND_*
 *  decision is made.
 */

namespace PKP\mail\mailables;

use APP\decision\Decision;
use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\ReviewerComments;
use PKP\mail\traits\Sender;

class RecommendationNotifyEditors extends Mailable
{
    use Recipient;
    use ReviewerComments;
    use Sender;

    protected static ?string $name = 'mailable.decision.recommendation.notifyEditors.name';
    protected static ?string $description = 'mailable.decision.recommendation.notifyEditors.description';
    protected static ?string $emailTemplateKey = 'EDITOR_RECOMMENDATION';
    protected static bool $supportsTemplates = true;
    protected static array $groupIds = [self::GROUP_REVIEW];

    /**
     * @param array<ReviewAssignment> $reviewAssignments
     */
    public function __construct(Context $context, Submission $submission, Decision $decision, array $reviewAssignments)
    {
        parent::__construct(array_slice(func_get_args(), 0, -1));
        $this->setupReviewerCommentsVariable($reviewAssignments, $submission);
    }

    public static function getDataDescriptions(): array
    {
        $variables = parent::getDataDescriptions();
        return self::addReviewerCommentsDescription($variables);
    }
}
