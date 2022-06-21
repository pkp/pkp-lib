<?php

namespace PKP\decision\types\contracts;

use APP\submission\Submission;

/**
 * Determine if decision can taken to remove the current stage/state
 *
 * @param  \APP\submission\Submission   $submission
 * @param  int                          $reviewRoundId
 *
 * @return bool Result to decide if a removable decision can be taken or not
 */
interface DecisionRemovable
{
    public function canRemove(Submission $submission, ?int $reviewRoundId): bool;
}
