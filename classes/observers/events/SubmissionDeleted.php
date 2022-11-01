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

use PKP\submission\PKPSubmission;

class SubmissionDeleted
{
    use Dispatchable;

    /**
     * The submission to delete
     */
    public PKPSubmission $submission;

    public function __construct(PKPSubmission $submission)
    {
        $this->submission = $submission;
    }
}
