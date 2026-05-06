<?php

/**
 * @file classes/submission/reviewRound/enums/PublicReviewStatus.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @enum PublicReviewStatus
 *
 * @ingroup submission_reviewRound
 *
 * @brief Public-facing open peer review status.
 */

namespace PKP\submission\reviewRound\enums;

enum PublicReviewStatus: string
{
    /** No review assignments are present, or no present review assignments have been accepted yet */
    case NotStarted = 'notStarted';
    /** At least one review assignment has been accepted */
    case InProgress = 'inProgress';
    /** All review assignments in round that were not declined or canceled have been completed */
    case Complete = 'complete';
}
