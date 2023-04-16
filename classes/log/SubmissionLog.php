<?php

/**
 * @defgroup log Log
 * Implements submission event logs.
 */

/**
 * @file classes/log/SubmissionLog.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionLog
 *
 * @ingroup log
 *
 * @brief Static class for adding / accessing PKP log entries.
 */

namespace PKP\log;

use APP\facades\Repo;
use PKP\core\Core;
use PKP\db\DAORegistry;
use PKP\security\Validation;
use PKP\session\SessionManager;

class SubmissionLog
{
    /**
     * Add a new event log entry with the specified parameters
     *
     * @param object $request
     * @param object $submission
     * @param int $eventType
     * @param string $messageKey
     * @param array $params optional
     *
     * @return object SubmissionLogEntry iff the event was logged
     */
    public static function logEvent($request, $submission, $eventType, $messageKey, $params = [])
    {
        // Create a new entry object
        $submissionEventLogDao = DAORegistry::getDAO('SubmissionEventLogDAO'); /** @var SubmissionEventLogDAO $submissionEventLogDao */
        $entry = $submissionEventLogDao->newDataObject();

        // Set implicit parts of the log entry
        $entry->setDateLogged(Core::getCurrentDate());

        if (Validation::isLoggedInAs()) {
            // If user is logged in as another user log with real userid
            $sessionManager = SessionManager::getManager();
            $session = $sessionManager->getUserSession();
            $userId = $session->getSessionVar('signedInAs');
            if ($userId) {
                $entry->setUserId($userId);
            }
        } else {
            $user = $request->getUser();
            if ($user) {
                $entry->setUserId($user->getId());
            }
        }

        $entry->setSubmissionId($submission->getId());

        // Set explicit parts of the log entry
        $entry->setEventType($eventType);
        $entry->setMessage($messageKey);
        $entry->setParams($params);
        $entry->setIsTranslated(0); // Legacy for old entries. All messages now use locale keys.

        // Insert the resulting object
        $submissionEventLogDao->insertObject($entry);

        // Stamp the submission status modification date without triggering edit event
        $submission->stampLastActivity();
        Repo::submission()->dao->update($submission);

        return $entry;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\log\SubmissionLog', '\SubmissionLog');
}
