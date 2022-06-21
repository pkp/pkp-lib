<?php

namespace PKP\decision\types\contracts;

use APP\submission\Submission;

/**
 * Determine if decision can be retractable that is to backout to previous state/stage
 *
 * @param  \APP\submission\Submission   $submission
 * @param  int                          $reviewRoundId
 *
 * @return bool Result to decide if decision can be retractable
 */
interface DecisionRetractable
{
    public function canRetract(Submission $submission, ?int $reviewRoundId): bool;
}
