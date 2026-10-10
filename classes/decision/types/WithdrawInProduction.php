<?php
/**
 * @file classes/decision/types/WithdrawInProduction.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2000-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WithdrawInProduction
 *
 * @brief A decision to withdraw a submission in the production stage.
 */

namespace PKP\decision\types;

use APP\decision\Decision;

class WithdrawInProduction extends Withdraw
{
    public function getDecision(): int
    {
        return Decision::WITHDRAW_PRODUCTION;
    }

    public function getStageId(): int
    {
        return WORKFLOW_STAGE_ID_PRODUCTION;
    }
}
