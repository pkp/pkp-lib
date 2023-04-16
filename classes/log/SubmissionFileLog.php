<?php

/**
 * @file classes/log/SubmissionFileLog.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileLog
 *
 * @ingroup log
 *
 * @brief Static class for adding / accessing submission file log entries.
 */

namespace PKP\log;

use APP\core\Application;
use PKP\core\Core;
use PKP\db\DAORegistry;

class SubmissionFileLog extends SubmissionLog
{
    /**
     * Add a new file event log entry with the specified parameters
     *
     * @param object $request
     * @param object $submissionFile
     * @param int $eventType
     * @param string $messageKey
     * @param array $params optional
     *
     * @return object SubmissionLogEntry iff the event was logged
     */
    public static function logEvent($request, $submissionFile, $eventType, $messageKey, $params = [])
    {
        // Create a new entry object
        $submissionFileEventLogDao = DAORegistry::getDAO('SubmissionFileEventLogDAO'); /** @var SubmissionFileEventLogDAO $submissionFileEventLogDao */
        $entry = $submissionFileEventLogDao->newDataObject();

        // Set implicit parts of the log entry
        $entry->setDateLogged(Core::getCurrentDate());

        $user = $request->getUser();
        if ($user) {
            $entry->setUserId($user->getId());
        }

        $entry->setAssocType(Application::ASSOC_TYPE_SUBMISSION_FILE);
        $entry->setAssocId($submissionFile->getId());

        // Set explicit parts of the log entry
        $entry->setEventType($eventType);
        $entry->setMessage($messageKey);
        $entry->setParams($params);
        $entry->setIsTranslated(0); // Legacy for other apps. All messages use locale keys.

        // Insert the resulting object
        $submissionFileEventLogDao->insertObject($entry);
        return $entry;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\log\SubmissionFileLog', '\SubmissionFileLog');
}
