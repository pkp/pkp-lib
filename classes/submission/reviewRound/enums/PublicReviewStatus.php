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
    case NotStarted = 'notStarted';
    case InProgress = 'inProgress';
    case Complete = 'complete';
}
