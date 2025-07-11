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
 *
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
    public const INTERNAL_REVIEW = 1;
    public const ACCEPT = 2;
    public const EXTERNAL_REVIEW = 3;
    public const PENDING_REVISIONS = 4;
    public const RESUBMIT = 5;
    public const DECLINE = 6;
    public const SEND_TO_PRODUCTION = 7;
    public const INITIAL_DECLINE = 8;
    public const RECOMMEND_ACCEPT = 9;
    public const RECOMMEND_PENDING_REVISIONS = 10;
    public const RECOMMEND_RESUBMIT = 11;
    public const RECOMMEND_DECLINE = 12;
    public const RECOMMEND_EXTERNAL_REVIEW = 13;            // OMP Specific
    public const NEW_EXTERNAL_ROUND = 14;
    public const REVERT_DECLINE = 15;
    public const REVERT_INITIAL_DECLINE = 16;
    public const SKIP_EXTERNAL_REVIEW = 17;
    public const SKIP_INTERNAL_REVIEW = 18;                 // OMP Specific
    public const ACCEPT_INTERNAL = 19;                      // OMP Specific
    public const PENDING_REVISIONS_INTERNAL = 20;           // OMP Specific
    public const RESUBMIT_INTERNAL = 21;                    // OMP Specific
    public const DECLINE_INTERNAL = 22;                     // OMP Specific
    public const RECOMMEND_ACCEPT_INTERNAL = 23;            // OMP Specific
    public const RECOMMEND_PENDING_REVISIONS_INTERNAL = 24; // OMP Specific
    public const RECOMMEND_RESUBMIT_INTERNAL = 25;          // OMP Specific
    public const RECOMMEND_DECLINE_INTERNAL = 26;           // OMP Specific
    public const REVERT_INTERNAL_DECLINE = 27;              // OMP Specific
    public const NEW_INTERNAL_ROUND = 28;                   // OMP Specific
    public const BACK_FROM_PRODUCTION = 29;
    public const BACK_FROM_COPYEDITING = 30;
    public const CANCEL_REVIEW_ROUND = 31;
    public const CANCEL_INTERNAL_REVIEW_ROUND = 32;         // OMP Specific


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
