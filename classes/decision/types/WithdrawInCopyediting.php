<?php
/**
 * @file classes/decision/types/WithdrawInCopyediting.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2000-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WithdrawInCopyediting
 *
 * @brief A decision to withdraw a submission in the copyediting stage.
 */

namespace PKP\decision\types;

use APP\decision\Decision;

class WithdrawInCopyediting extends Withdraw
{
    public function getDecision(): int
    {
        return Decision::WITHDRAW_COPYEDITING;
    }

    public function getStageId(): int
    {
        return WORKFLOW_STAGE_ID_EDITING;
    }
}
