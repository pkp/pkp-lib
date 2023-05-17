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
use APP\facades\Repo;
use Illuminate\Events\Dispatcher;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\observers\events\SubmissionSubmitted;
use PKP\security\Validation;

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
        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
            'assocId' => $event->submission->getId(),
            'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_SUBMISSION_SUBMIT,
            'userId' => Validation::loggedInAs() ?? Application::get()->getRequest()->getUser()?->getId(),
            'message' => 'submission.event.submissionSubmitted',
            'isTranslated' => 0,
            'dateLogged' => Core::getCurrentDate(),
        ]);
        Repo::eventLog()->add($eventLog);
    }
}
