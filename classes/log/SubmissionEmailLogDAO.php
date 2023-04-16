<?php

/**
 * @file classes/log/SubmissionEmailLogDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionEmailLogDAO
 *
 * @ingroup log
 *
 * @see EmailLogDAO
 *
 * @brief Extension to EmailLogDAO for submission-specific log entries.
 */

namespace PKP\log;

use APP\core\Application;
use APP\submission\Submission;
use Illuminate\Support\Facades\Mail;
use PKP\core\Core;
use PKP\facades\Locale;
use PKP\mail\Mailable;
use PKP\user\User;

class SubmissionEmailLogDAO extends EmailLogDAO
{
    /**
     * Instantiate and return a SubmissionEmailLogEntry
     *
     * @return SubmissionEmailLogEntry
     */
    public function newDataObject()
    {
        $returner = new SubmissionEmailLogEntry();
        $returner->setAssocType(Application::ASSOC_TYPE_SUBMISSION);
        return $returner;
    }

    /**
     * Get submission email log entries by submission ID and event type
     *
     * @param int $submissionId
     * @param int $userId optional Return only emails sent to this user.
     *
     * @return DAOResultFactory
     */
    public function getByEventType($submissionId, $eventType, $userId = null)
    {
        return parent::_getByEventType(Application::ASSOC_TYPE_SUBMISSION, $submissionId, $eventType, $userId);
    }

    /**
     * Get submission email log entries by submission ID
     *
     * @param int $submissionId
     *
     * @return DAOResultFactory
     */
    public function getBySubmissionId($submissionId)
    {
        return $this->getByAssoc(Application::ASSOC_TYPE_SUBMISSION, $submissionId);
    }

    /**
     * Create a log entry from data in a Mailable class
     *
     * @param int $eventType One of the SubmissionEmailLogEntry::SUBMISSION_EMAIL_* constants
     *
     * @return int The new log entry id
     */
    public function logMailable(int $eventType, Mailable $mailable, Submission $submission, ?User $sender = null): int
    {
        $entry = $this->newDataObject();
        $clonedMailable = clone $mailable;
        $clonedMailable->removeFooter();
        $entry->setEventType($eventType);
        $entry->setAssocId($submission->getId());
        $entry->setDateSent(Core::getCurrentDate());
        $entry->setSenderId($sender ? $sender->getId() : 0);
        $entry->setSubject(Mail::compileParams(
            $clonedMailable->subject,
            $clonedMailable->getData(Locale::getLocale())
        ));
        $entry->setBody($clonedMailable->render());
        $entry->setFrom($this->getContactString($clonedMailable->from));
        $entry->setRecipients($this->getContactString($clonedMailable->to));
        $entry->setCcs($this->getContactString($clonedMailable->cc));
        $entry->setBccs($this->getContactString($clonedMailable->bcc));

        return $this->insertObject($entry);
    }

    /**
     * Get the from or to data as a string
     *
     * @param array $addressees Expects Mailable::$to or Mailable::$from
     */
    protected function getContactString(array $addressees): string
    {
        $contactStrings = [];
        foreach ($addressees as $addressee) {
            $contactStrings[] = isset($addressee['name'])
                ? '"' . $addressee['name'] . '" <' . $addressee['address'] . '>'
                : $addressee['address'];
        }
        return join(', ', $contactStrings);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\log\SubmissionEmailLogDAO', '\SubmissionEmailLogDAO');
}
