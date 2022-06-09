<?php

namespace PKP\decision\types\contracts;

use APP\submission\Submission;

interface RemoveRreviewRoundDecisionTypeContract
{
    /**
     * Determine if review round can be removed/deleted
     *
     * @param  \APP\submission\Submission   $submission     Review round associated submission
     * @param  int                          $reviewRoundId  Target review round id that need to be removed
     *
     * @return bool Result to decide if review round can be removed
     */
    public static function canRemove(Submission $submission, int $reviewRoundId): bool;
}
