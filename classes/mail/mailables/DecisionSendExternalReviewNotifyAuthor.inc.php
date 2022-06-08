<?php

/**
 * @file classes/mail/mailables/DecisionSendExternalReviewNotifyAuthor.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DecisionSendExternalReviewNotifyAuthor
 *
 * @brief Email sent to the author(s) when a SUBMISSION_EDITOR_DECISION_EXTERNAL_REVIEW
 *  decision is made.
 */

namespace PKP\mail\mailables;

use APP\decision\Decision;
use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;
use PKP\security\Role;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\mail\traits\Configurable;

class DecisionSendExternalReviewNotifyAuthor extends Mailable
{
    use Configurable;
    use Recipient;
    use Sender;

    public const REVIEW_TYPE_DESCRIPTION_VARIABLE = 'reviewTypeDescription';

    protected static ?string $name = 'mailable.decision.sendExternalReview.notifyAuthor.name';
    protected static ?string $description = 'mailable.decision.sendExternalReview.notifyAuthor.description';
    protected static ?string $emailTemplateKey = 'EDITOR_DECISION_SEND_TO_EXTERNAL';
    protected static bool $supportsTemplates = true;
    protected static array $groupIds = [self::GROUP_SUBMISSION];
    protected static array $fromRoleIds = [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR];
    protected static array $toRoleIds = [Role::ROLE_ID_AUTHOR];

    public function __construct(Context $context, Submission $submission, Decision $decision)
    {
        parent::__construct(func_get_args());
        $this->setupReviewTypeVariable($context);
    }

    public static function getDataDescriptions(): array
    {
        return array_merge([
            parent::getDataDescriptions(),
            [
                static::REVIEW_TYPE_DESCRIPTION_VARIABLE => __('emailTemplate.variable.reviewType'),
            ]
        ]);
    }

    protected function setupReviewTypeVariable(Context $context)
    {
        switch ($context->getData('defaultReviewMode')) {
            case ReviewAssignment::SUBMISSION_REVIEW_METHOD_ANONYMOUS:
                $description = __('emailTemplate.variable.reviewType.anonymous');
                break;
            case ReviewAssignment::SUBMISSION_REVIEW_METHOD_OPEN:
                $description = __('emailTemplate.variable.reviewType.open');
                break;
            default:
                $description = __('emailTemplate.variable.reviewType.doubleAnonymous');
        }

        $this->addData([
            static::REVIEW_TYPE_DESCRIPTION_VARIABLE => $description,
        ]);
    }
}
