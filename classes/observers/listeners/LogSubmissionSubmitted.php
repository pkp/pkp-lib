<?php
/**
 * @file classes/observers/listeners/LogSubmissionSubmitted.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LogSubmissionSubmitted
 *
 * @ingroup observers_listeners
 *
 * @brief Create an entry in the submission log when a submission is submitted
 */

namespace PKP\observers\listeners;

use APP\core\Application;
use APP\log\SubmissionEventLogEntry;
use Illuminate\Events\Dispatcher;
use PKP\log\SubmissionLog;
use PKP\observers\events\SubmissionSubmitted;

class LogSubmissionSubmitted
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            SubmissionSubmitted::class,
            LogSubmissionSubmitted::class
        );
    }

    public function handle(SubmissionSubmitted $event)
    {
        SubmissionLog::logEvent(
            Application::get()->getRequest(),
            $event->submission,
            SubmissionEventLogEntry::SUBMISSION_LOG_SUBMISSION_SUBMIT,
            'submission.event.submissionSubmitted'
        );
    }
}
