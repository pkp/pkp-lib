<?php

/**
 * @defgroup decision Decision
 */

/**
 * @file classes/decision/Decision.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
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
    public const INITIAL_DECLINE = 9;
    public const SKIP_REVIEW = 19;
    public const RECOMMEND_ACCEPT = 11;
    public const RECOMMEND_PENDING_REVISIONS = 12;
    public const RECOMMEND_RESUBMIT = 13;
    public const RECOMMEND_DECLINE = 14;
    public const REVERT_DECLINE = 17;
    public const REVERT_INITIAL_DECLINE = 18;
    public const BACK_TO_COPYEDITING = 21;
    public const BACK_TO_REVIEW = 20;
    public const BACK_TO_SUBMISSION_FROM_COPYEDITING = 22;
    public const SEND_TO_PRODUCTION = 7;

    /**
     * Get the decision type for this decision
     */
    public function getType(): Type
    {
        foreach (Repo::decision()->getTypes() as $type) {
            if ($type->getDecision() === $this->getData('decision')) {
                return $type;
            }
        }
        throw new Exception('Decision exists with an unknown type. Decision: ' . $this->getData('decisions'));
    }
}

if (!PKP_STRICT_MODE) {
    define('SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE', Decision::INITIAL_DECLINE);
    define('SUBMISSION_EDITOR_DECISION_SKIP_REVIEW', Decision::SKIP_REVIEW);
    define('SUBMISSION_EDITOR_RECOMMEND_ACCEPT', Decision::RECOMMEND_ACCEPT);
    define('SUBMISSION_EDITOR_RECOMMEND_PENDING_REVISIONS', Decision::RECOMMEND_PENDING_REVISIONS);
    define('SUBMISSION_EDITOR_RECOMMEND_RESUBMIT', Decision::RECOMMEND_RESUBMIT);
    define('SUBMISSION_EDITOR_RECOMMEND_DECLINE', Decision::RECOMMEND_DECLINE);
    define('SUBMISSION_EDITOR_DECISION_REVERT_DECLINE', Decision::REVERT_DECLINE);
    define('SUBMISSION_EDITOR_DECISION_REVERT_INITIAL_DECLINE', Decision::REVERT_INITIAL_DECLINE);
    define('SUBMISSION_EDITOR_DECISION_BACK_TO_COPYEDITING', Decision::BACK_TO_COPYEDITING);
    define('SUBMISSION_EDITOR_DECISION_BACK_TO_REVIEW', Decision::BACK_TO_REVIEW);
    define('SUBMISSION_EDITOR_DECISION_BACK_TO_SUBMISSION_FROM_COPYEDITING', Decision::BACK_TO_SUBMISSION_FROM_COPYEDITING);
    define('SUBMISSION_EDITOR_DECISION_SEND_TO_PRODUCTION', Decision::SEND_TO_PRODUCTION);
}
