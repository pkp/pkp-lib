<?php

declare(strict_types=1);

/**
 * @file classes/observers/listeners/DeletedSubmissionListener.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DeletedSubmissionListener
 * @ingroup core
 *
 * @brief Listener fired when submission's deleted
 */

namespace PKP\observers\listeners;

use PKP\Jobs\Submissions\DeletedSubmissionSearchJob;
use PKP\observers\events\DeletedSubmission;

class DeletedSubmissionListener
{
    /**
     * Handle the listener call
     *
     *
     */
    public function handle(DeletedSubmission $event)
    {
        dispatch(new DeletedSubmissionSearchJob($event->submission->getId()));
    }
}
