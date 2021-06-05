<?php

declare(strict_types=1);

/**
 * @file classes/observers/listeners/DeleteSubmissionFileListener.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DeletedSubmission
 * @ingroup core
 *
 * @brief Listener fired when submission file's deleted
 */

namespace PKP\observers\listeners;

use PKP\Jobs\Submissions\DeleteSubmissionFileJob;
use PKP\observers\events\DeleteSubmissionFile;

class DeleteSubmissionFileListener
{
    /**
     * Handle the listener call
     *
     *
     */
    public function handle(DeleteSubmissionFile $event)
    {
        dispatch(new DeleteSubmissionFileJob($event->submissionFile->getId()));
    }
}
