<?php

/**
 * @file classes/mail/mailables/RequestReviewRoundAuthorResponse.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RequestReviewRoundAuthorResponse
 *
 * @brief Email sent to the author(s) when requesting an author response to reviewers' comments. Uses the same email template as the DecisionRequestRevisionsNotifyAuthor mailable, but specifically for
 * sending the email template outside of the context of a decision, and without having to create/have a Decision object, which is required when using DecisionRequestRevisionsNotifyAuthor mailable.
 */

namespace PKP\mail\mailables;

use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\ReviewerComments;
use PKP\mail\traits\ReviewRoundAuthorResponse;
use PKP\mail\traits\Sender;
use PKP\security\Role;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewRound\ReviewRound;

class RequestReviewRoundAuthorResponse extends Mailable
{
    use Configurable;
    use Recipient;
    use ReviewerComments;
    use Sender;
    use ReviewRoundAuthorResponse;

    protected static ?string $name = 'mailable.reviewRound.requestAuthorResponse.name';
    protected static ?string $description = 'mailable.reviewRound.requestAuthorResponse.description';
    protected static ?string $emailTemplateKey = 'EDITOR_DECISION_REVISIONS';
    protected static bool $supportsTemplates = false;
    protected static array $groupIds = [self::GROUP_REVIEW];
    protected static array $fromRoleIds = [Role::ROLE_ID_SUB_EDITOR];
    protected static array $toRoleIds = [Role::ROLE_ID_AUTHOR];

    /**
     * @param array<ReviewAssignment> $reviewAssignments
     */
    public function __construct(Context $context, Submission $submission, array $reviewAssignments, ReviewRound $reviewRound)
    {
        parent::__construct(array_slice(func_get_args(), 0, -2));
        $this->setupReviewerCommentsVariable($reviewAssignments, $submission);
        $this->setupReviewAuthorResponseVariables($submission, $reviewRound->getId(), $reviewRound->getStageId(), $context);
    }

    public static function getDataDescriptions(): array
    {
        $variables = parent::getDataDescriptions();
        $variables = self::addReviewerCommentsDescription($variables);
        $variables = self::addReviewAuthorResponseDataDescriptions($variables);

        return $variables;
    }
}
