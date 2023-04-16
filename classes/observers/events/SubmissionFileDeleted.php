<?php

declare(strict_types=1);

/**
 * @file classes/observers/events/SubmissionFileDeleted.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileDeleted
 *
 * @ingroup core
 *
 * @brief Event fired when submission's deleted
 */

namespace PKP\observers\events;

use Illuminate\Foundation\Events\Dispatchable;

class SubmissionFileDeleted
{
    use Dispatchable;

    /**
     * The submission id of the targeted submission
     */
    public int $submissionId;

    /**
     * The submission file id of the targeted submission file to delete
     */
    public int $submissionFileId;


    public function __construct(int $submissionId, int $submissionFileId)
    {
        $this->submissionId = $submissionId;
        $this->submissionFileId = $submissionFileId;
    }
}
