<?php

/**
 * @file classes/mail/mailables/ReviewerUnassign.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerUnassign
 * @ingroup mail_mailables
 *
 * @brief Email sent when a reviewer is unassigned
 */

namespace PKP\mail\mailables;

use APP\decision\Decision;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;
use PKP\security\Role;
use PKP\submission\PKPSubmission;
use PKP\submission\reviewAssignment\ReviewAssignment;

class ReviewerUnassign extends Mailable
{
    use Recipient;
    use Sender;
    use Configurable;

    /** @var string An email variable that contains a description of the editorial decision */
    public const DECISION_DESCRIPTION = 'decisionDescription';

    protected static ?string $name = 'mailable.reviewerUnassign.name';
    protected static ?string $description = 'mailable.reviewerUnassign.description';
    protected static ?string $emailTemplateKey = 'REVIEW_CANCEL';
    protected static bool $supportsTemplates = true;
    protected static array $groupIds = [self::GROUP_REVIEW];
    protected static bool $canDisable = true;
    protected static array $fromRoleIds = [Role::ROLE_ID_MANAGER];
    protected static array $toRoleIds = [Role::ROLE_ID_REVIEWER];

    protected ?Decision $decision;

    public function __construct(
        Context $context,
        PKPSubmission $submission,
        ?ReviewAssignment $reviewAssignment = null,
        ?Decision $decision = null
    ) {
        parent::__construct(array_filter(func_get_args(), fn ($param) => !is_null($param)));
        $this->decision = $decision;
    }

    public function getDecision(): ?Decision
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

        if ($this->decision) {
            $this->viewData[self::DECISION_DESCRIPTION] = $this->getDecisionDescription($locale);
        }
    }

    /**
     * Get a description of the decision to use as an email variable
     */
    protected function getDecisionDescription(?string $locale = null): string
    {
        $class = Decision::class;
        $reviewerUnassignedTypes = collect([
            'BACK_FROM_EXTERNAL_REVIEW',
            'BACK_FROM_INTERNAL_REVIEW',
        ])
            ->map(fn ($type) => defined("{$class}::{$type}") ? constant("{$class}::{$type}") : null)
            ->filter(fn ($type) => !is_null($type))
            ->toArray();

        if (in_array($this->decision->getData('decision'), $reviewerUnassignedTypes)) {
            return __('mailable.decision.notifyReviewer.variable.decisionDescription.unassigned', [], $locale);
        }

        return '';
    }
}
