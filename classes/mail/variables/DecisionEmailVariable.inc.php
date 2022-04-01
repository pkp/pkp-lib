<?php

/**
 * @file classes/mail/variables/DecisionEmailVariable.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DecisionEmailVariable
 * @ingroup mail_variables
 *
 * @brief Represents variables associated with an editorial decision
 */

namespace PKP\mail\variables;

use APP\decision\Decision;
use APP\facades\Repo;
use PKP\decision\DecisionType;
use PKP\workflow\WorkflowStageDAO;

class DecisionEmailVariable extends Variable
{
    public const DECISION = 'decision';
    public const DESCRIPTION = 'decisionDescription';
    public const STAGE = 'decisionStage';
    public const ROUND = 'decisionReviewRound';

    protected Decision $decision;
    protected DecisionType $decisionType;

    public function __construct(Decision $decision)
    {
        $this->decision = $decision;
        $this->decisionType = Repo::decision()->getDecisionType($decision->getData('decision'));
    }

    /**
     * @copydoc Variable::descriptions()
     */
    public static function descriptions(): array
    {
        return
        [
            self::DECISION => __('emailTemplate.variable.decision.name'),
            self::DESCRIPTION => __('emailTemplate.variable.decision.description'),
            self::STAGE => __('emailTemplate.variable.decision.stage'),
            self::ROUND => __('emailTemplate.variable.decision.round'),
        ];
    }

    /**
     * @copydoc Variable::values()
     */
    public function values(string $locale): array
    {
        return
        [
            self::DECISION => $this->decisionType->getLabel($locale),
            self::DESCRIPTION => $this->decisionType->getDescription($locale),
            self::STAGE => $this->getStageName($locale),
            self::ROUND => (string) $this->decision->getData('round'),
        ];
    }

    protected function getStageName(string $locale): string
    {
        return __(
            (string) WorkflowStageDAO::getTranslationKeyFromId($this->decision->getData('stageId')),
            [],
            $locale
        );
    }
}
