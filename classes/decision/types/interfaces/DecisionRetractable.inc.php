<?php

namespace PKP\decision\types\interfaces;

use APP\submission\Submission;

interface DecisionRetractable
{
    /**
     * Determine if decision can be retractable that is to backout to previous state/stage
     *
     * @return bool Result to decide if decision can be retractable
     */
    public function canRetract(Submission $submission, ?int $reviewRoundId): bool;

    /**
     * Determine/Deduce the backoutable stage id for this decision
     *
     * @return int|null The possible backout stage id for this decision
     */
    public function deduceBackoutableStageId(Submission $submission, ?int $reviewRoundId): ?int;
}
