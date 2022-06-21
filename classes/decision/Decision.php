<?php

/**
 * @defgroup decision Decision
 */

/**
 * @file classes/decision/Decision.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Decision
 * @ingroup decision
 *
 * @see DAO
 *
 * @brief An editorial decision taken on a submission, such as to accept, decline or request revisions.
 */

namespace PKP\decision;

use APP\facades\Repo;
use Exception;
use PKP\core\DataObject;

class Decision extends DataObject
{
    public const SEND_TO_PRODUCTION = 7;
    public const INITIAL_DECLINE = 9;
    public const RECOMMEND_ACCEPT = 11;
    public const RECOMMEND_PENDING_REVISIONS = 12;
    public const RECOMMEND_RESUBMIT = 13;
    public const RECOMMEND_DECLINE = 14;
    public const NEW_EXTERNAL_ROUND = 16;
    public const REVERT_DECLINE = 17;
    public const REVERT_INITIAL_DECLINE = 18;
    public const SKIP_EXTERNAL_REVIEW = 19;
    public const BACK_FROM_PRODUCTION = 31;
    public const BACK_FROM_COPYEDITING = 32;
    public const CANCEL_REVIEW_ROUND = 33;


    /**
     * Get the decision type for this decision
     */
    public function getDecisionType(): DecisionType
    {
        $decisionType = Repo::decision()->getDecisionType($this->getData('decision'));
        if (!$decisionType) {
            throw new Exception('Decision exists with an unknown type. Decision: ' . $this->getData('decisions'));
        }
        return $decisionType;
    }
}

if (!PKP_STRICT_MODE) {
    // Some constants are not redefined here because they never existed as global constants
    define('SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE', Decision::INITIAL_DECLINE);
    define('SUBMISSION_EDITOR_RECOMMEND_ACCEPT', Decision::RECOMMEND_ACCEPT);
    define('SUBMISSION_EDITOR_RECOMMEND_PENDING_REVISIONS', Decision::RECOMMEND_PENDING_REVISIONS);
    define('SUBMISSION_EDITOR_RECOMMEND_RESUBMIT', Decision::RECOMMEND_RESUBMIT);
    define('SUBMISSION_EDITOR_RECOMMEND_DECLINE', Decision::RECOMMEND_DECLINE);
    define('SUBMISSION_EDITOR_DECISION_REVERT_DECLINE', Decision::REVERT_DECLINE);
    define('SUBMISSION_EDITOR_DECISION_SEND_TO_PRODUCTION', Decision::SEND_TO_PRODUCTION);
    define('SUBMISSION_EDITOR_DECISION_NEW_ROUND', Decision::NEW_EXTERNAL_ROUND);
}
