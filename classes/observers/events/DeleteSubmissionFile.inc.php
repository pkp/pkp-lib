<?php

/**
 * @file classes/observers/events/DeleteSubmissionFile.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DeleteSubmissionFile
 * @ingroup core
 *
 * @brief Event fired when submission's deleted
 */

namespace PKP\observers\events;

use Illuminate\Foundation\Events\Dispatchable;

use PKP\Jobs\Submissions\DeleteSubmissionFileJob;
use PKP\submission\SubmissionFile;

class DeleteSubmissionFile
{
    use Dispatchable;

    /** @var SubmissionFile $submissionFile Submission file associated */
    public $submissionFile;

    public function __construct(SubmissionFile $submissionFile)
    {
        $this->submissionFile = $submissionFile;

        dispatch(new DeleteSubmissionFileJob($this->submissionFile->getId()));
    }
}
