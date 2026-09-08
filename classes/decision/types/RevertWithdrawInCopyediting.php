<?php
/**
 * @file classes/decision/types/RevertWithdrawInCopyediting.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2000-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RevertWithdrawInCopyediting
 *
 * @brief A decision to revert a withdrawal when the submission was withdrawn
 *   from the copyediting stage.
 */

namespace PKP\decision\types;

use APP\decision\Decision;

class RevertWithdrawInCopyediting extends RevertWithdraw
{
    public function getDecision(): int
    {
        return Decision::REVERT_WITHDRAW_COPYEDITING;
    }

    public function getStageId(): int
    {
        return WORKFLOW_STAGE_ID_EDITING;
    }
}
