<?php

declare(strict_types=1);

/**
 * @file classes/observers/events/SubmissionDeleted.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionDeleted
 * @ingroup core
 *
 * @brief Event fired when submission's deleted
 */

namespace PKP\observers\events;

use Illuminate\Foundation\Events\Dispatchable;

class SubmissionDeleted
{
    use Dispatchable;

    /**
     * The submission id of the targeted submission to delete
     */
    public int $submissionId;

    public function __construct(int $submissionId)
    {
        $this->submissionId = $submissionId;
    }
}
