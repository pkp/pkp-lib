<?php
/**
 * @file classes/decision/types/interfaces/DecisionRetractable.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief Helper functions to determine if a decision to back out of a review
 *   round or stage can be recorded
 */

namespace PKP\decision\types\interfaces;

use APP\submission\Submission;

interface DecisionRetractable
{
    /**
     * Determine if a decision to back out of a review round or stage can be recorded
     *
     * @return bool Result to decide if decision can be retractable
     */
    public function canRetract(Submission $submission, ?int $reviewRoundId): bool;
}
