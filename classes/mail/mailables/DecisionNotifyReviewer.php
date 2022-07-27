<?php

/**
 * @file classes/mail/mailables/DecisionNotifyReviewer.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DecisionNotifyReviewer
 *
 * @brief Email sent to the reviewers who have completed a review in the review round
 *  when a SUBMISSION_EDITOR_DECISION_ACCEPT decision is made.
 */

namespace PKP\mail\mailables;

use APP\decision\Decision;
use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;
use PKP\security\Role;

class DecisionNotifyReviewer extends Mailable
{
    use Configurable;
    use Recipient;
    use Sender;

    /** @var string An email variable that contains a description of the editorial decision */
    public const DECISION_DESCRIPTION = 'decisionDescription';

    protected static ?string $name = 'mailable.decision.notifyReviewer.name';
    protected static ?string $description = 'mailable.decision.notifyReviewer.description';
    protected static ?string $emailTemplateKey = 'REVIEW_ACK';
    protected static bool $supportsTemplates = true;
    protected static array $groupIds = [self::GROUP_REVIEW];
    protected static bool $canDisable = true;
    protected static array $fromRoleIds = [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR];
    protected static array $toRoleIds = [Role::ROLE_ID_REVIEWER];
    protected Decision $decision;

    public function __construct(Context $context, Submission $submission, Decision $decision)
    {
        $this->decision = $decision;
        parent::__construct(func_get_args());
    }

    public function getDecision(): Decision
    {
        return $this->decision;
    }

    public static function getDataDescriptions(): array
    {
        $variables = parent::getDataDescriptions();
        $variables[self::DECISION_DESCRIPTION] = __('mailable.decision.notifyReviewer.variable.decisionDescription');
        return $variables;
    }

    public function setData(?string $locale = null)
    {
        parent::setData($locale);
        $this->viewData[self::DECISION_DESCRIPTION] = $this->getDecisionDescription($locale);
    }

    /**
     * Get a description of the decision to use as an email variable
     */
    protected function getDecisionDescription(?string $locale = null): string
    {
        switch ($this->decision->getData('decision')) {
            case Decision::ACCEPT: return __('mailable.decision.notifyReviewer.variable.decisionDescription.accept', [], $locale);
            case Decision::DECLINE: return __('mailable.decision.notifyReviewer.variable.decisionDescription.decline', [], $locale);
            case Decision::PENDING_REVISIONS: return __('mailable.decision.notifyReviewer.variable.decisionDescription.pendingRevisions', [], $locale);
            case Decision::RESUBMIT: return __('mailable.decision.notifyReviewer.variable.decisionDescription.resubmit', [], $locale);
            default: return '';
        }
    }
}
