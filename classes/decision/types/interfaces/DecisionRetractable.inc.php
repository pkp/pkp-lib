<?php

namespace PKP\decision\types\interfaces;

use APP\submission\Submission;

interface DecisionRetractable
{
    /**
     * Determine if decision can be retractable that is to backout to previous state/stage
     *
     * @param  int $reviewRoundId
     *
     * @return bool Result to decide if decision can be retractable
     */
    public function canRetract(Submission $submission, ?int $reviewRoundId): bool;
}
