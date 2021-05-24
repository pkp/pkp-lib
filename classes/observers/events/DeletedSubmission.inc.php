<?php

/**
 * @file classes/observers/events/DeletedSubmission.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DeletedSubmission
 * @ingroup core
 *
 * @brief Event fired when submission's deleted
 */

namespace PKP\observers\events;

use Illuminate\Foundation\Events\Dispatchable;

use PKP\Jobs\Submissions\DeletedSubmissionSearchJob;
use PKP\submission\PKPSubmission;

class DeletedSubmission
{
    use Dispatchable;

    /** @var PKPSubmission $submission Submission associated */
    public $submission;

    public function __construct(PKPSubmission $submission)
    {
        $this->submission = $submission;

        dispatch(new DeletedSubmissionSearchJob($submission->getId()));
    }
}
