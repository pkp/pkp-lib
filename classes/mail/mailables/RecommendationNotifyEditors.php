<?php

/**
 * @file classes/mail/mailables/RecommendationNotifyEditors.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RecommendationNotifyEditors
 *
 * @brief Message sent to deciding editors when any SUBMISSION_EDITOR_RECOMMEND_*
 *  decision is made.
 */

namespace PKP\mail\mailables;

use PKP\decision\Decision;
use APP\facades\Repo;
use APP\submission\Submission;
use Exception;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\ReviewerComments;
use PKP\mail\traits\Sender;
use PKP\security\Role;
use PKP\submission\reviewAssignment\ReviewAssignment;

class RecommendationNotifyEditors extends Mailable
{
    use Configurable;
    use Recipient;
    use ReviewerComments;
    use Sender;

    public const RECOMMENDATION_VARIABLE = 'recommendation';

    protected static ?string $name = 'mailable.decision.recommendation.notifyEditors.name';
    protected static ?string $description = 'mailable.decision.recommendation.notifyEditors.description';
    protected static ?string $emailTemplateKey = 'EDITOR_RECOMMENDATION';
    protected static bool $supportsTemplates = true;
    protected static array $groupIds = [self::GROUP_REVIEW];
    protected static array $fromRoleIds = [Role::ROLE_ID_MANAGER];
    protected static array $toRoleIds = [Role::ROLE_ID_MANAGER];

    /**
     * @param array<ReviewAssignment> $reviewAssignments
     */
    public function __construct(Context $context, Submission $submission, Decision $decision, array $reviewAssignments)
    {
        parent::__construct(array_slice(func_get_args(), 0, -1));
        $this->setupRecommendationVariable($decision);
        $this->setupReviewerCommentsVariable($reviewAssignments, $submission);
    }

    public static function getDataDescriptions(): array
    {
        $variables = parent::getDataDescriptions();
        $variables[static::RECOMMENDATION_VARIABLE] = __('emailTemplate.variable.recommendation');
        return self::addReviewerCommentsDescription($variables);
    }

    protected function setupRecommendationVariable(Decision $decision)
    {
        $decisionType = Repo::decision()->getDecisionType($decision->getData('decision'));
        if (!$decisionType || !method_exists($decisionType, 'getRecommendationLabel')) {
            throw new Exception('Tried to get the recommendation from a decision that does not exist');
        }
        $this->addData([
            static::RECOMMENDATION_VARIABLE => $decisionType->getRecommendationLabel(),
        ]);
    }
}
