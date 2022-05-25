<?php

/**
 * @file classes/mail/mailables/DecisionRequestRevisionsNotifyAuthor.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DecisionRequestRevisionsNotifyAuthor
 *
 * @brief Email sent to the author(s) when a SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS
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
use PKP\security\Role;
use PKP\submission\reviewAssignment\ReviewAssignment;

class DecisionRequestRevisionsNotifyAuthor extends Mailable
{
    use Recipient;
    use ReviewerComments;
    use Sender;

    protected static ?string $name = 'mailable.decision.requestRevisions.notifyAuthor.name';
    protected static ?string $description = 'mailable.decision.requestRevisions.notifyAuthor.description';
    protected static ?string $emailTemplateKey = 'EDITOR_DECISION_REVISIONS';
    protected static bool $supportsTemplates = true;
    protected static array $groupIds = [self::GROUP_REVIEW];
    protected static array $fromRoleIds = [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR];
    protected static array $toRoleIds = [Role::ROLE_ID_AUTHOR];

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
