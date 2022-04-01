<?php

/**
 * @defgroup decision Decision
 */

/**
 * @file classes/decision/Decision.inc.php
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
    public const INITIAL_DECLINE = 9;
    public const REVERT_INITIAL_DECLINE = 18;

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
}
