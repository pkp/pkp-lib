<?php

/**
 * @file classes/log/event/EventLogEntry.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EventLogEntry
 *
 * @brief Describes an entry in the event log.
 */

namespace PKP\log\event;

use APP\core\Application;
use APP\facades\Repo;
use PKP\facades\Locale;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submissionFile\SubmissionFile;

class EventLogEntry extends \PKP\core\DataObject
{
    // Information Center events
    public const SUBMISSION_LOG_NOTE_POSTED = 16777216; // 0x01000000
    public const SUBMISSION_LOG_MESSAGE_SENT = 16777217; // 0x01000001

    //
    // Get/set methods
    //

    /**
     * Get user ID of user that initiated the event.
     */
    public function getUserId(): int
    {
        return $this->getData('userId');
    }

    /**
     * Set user ID of user that initiated the event.
     */
    public function setUserId(int $userId): void
    {
        $this->setData('userId', $userId);
    }

    /**
     * Get date entry was logged.
     */
    public function getDateLogged(): string
    {
        return $this->getData('dateLogged');
    }

    /**
     * Set date entry was logged.
     */
    public function setDateLogged(string $dateLogged): void
    {
        $this->setData('dateLogged', $dateLogged);
    }

    /**
     * Get event type.
     */
    public function getEventType(): int
    {
        return $this->getData('eventType');
    }

    /**
     * Set event type.
     */
    public function setEventType(int $eventType): void
    {
        $this->setData('eventType', $eventType);
    }

    /**
     * Get associated type.
     */
    public function getAssocType(): int
    {
        return $this->getData('assocType');
    }

    /**
     * Set associated type.
     */
    public function setAssocType(int $assocType): void
    {
        $this->setData('assocType', $assocType);
    }

    /**
     * Get associated ID.
     */
    public function getAssocId(): int
    {
        return $this->getData('assocId');
    }

    /**
     * Set associated ID.
     */
    public function setAssocId(int $assocId): void
    {
        $this->setData('assocId', $assocId);
    }

    /**
     * Get custom log message (either locale key or literal string).
     */
    public function getMessage(): string
    {
        return $this->getData('message');
    }

    /**
     * Set custom log message (either locale key or literal string).
     */
    public function setMessage(string $message): void
    {
        $this->setData('message', $message);
    }

    /**
     * Get flag indicating whether or not message is translated.
     *
     */
    public function getIsTranslated(): bool
    {
        return $this->getData('isTranslated');
    }

    /**
     * Set flag indicating whether or not message is translated.
     */
    public function setIsTranslated(bool $isTranslated)
    {
        $this->setData('isTranslated', $isTranslated);
    }

    /**
     * Get translated message, translating it if necessary.
     *
     * @param bool $hideReviewerName optional Don't reveal reviewer names in
     *  log descriptions.
     */
    public function getTranslatedMessage(?string $locale = null, bool $hideReviewerName = false): string
    {
        $message = $this->getMessage();
        // If it's already translated, just return the message.
        if ($this->getData('isTranslated')) {
            return $message;
        }

        // Otherwise, translate it and include parameters.
        if ($locale === null) {
            $locale = Locale::getLocale();
        }

        $eventLog = clone $this;

        if ($hideReviewerName) {
            // Reviewer activity log entries (assigning, accepting, declining)
            if ($eventLog->getData('reviewerName')) {
                $anonymousAuthor = true;
                if ($reviewAssignmentId = $eventLog->getData('reviewAssignmentId')) {
                    $reviewAssignment = Repo::reviewAssignment()->get($reviewAssignmentId);
                    if ($reviewAssignment && !in_array($reviewAssignment->getReviewMethod(), [ReviewAssignment::SUBMISSION_REVIEW_METHOD_ANONYMOUS, ReviewAssignment::SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS])) {
                        $anonymousAuthor = false;
                    }
                }
                if ($anonymousAuthor) {
                    $eventLog->setData('reviewerName', __('editor.review.anonymousReviewer'));
                }
            }
            // Files submitted by reviewers
            $fileStage = $eventLog->getData('fileStage');
            if ($fileStage && $fileStage === SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT) {
                $submissionFileId = $eventLog->getData('submissionFileId');
                assert($eventLog->getData('fileId') && $eventLog->getData('submissionId') && $submissionFileId);
                $anonymousAuthor = true;
                $submissionFile = Repo::submissionFile()->get($submissionFileId);
                if ($submissionFile && $submissionFile->getData('assocType') === Application::ASSOC_TYPE_REVIEW_ASSIGNMENT) {
                    $reviewAssignment = Repo::reviewAssignment()->get($submissionFile->getData('assocId'));
                    if ($reviewAssignment && !in_array($reviewAssignment->getReviewMethod(), [ReviewAssignment::SUBMISSION_REVIEW_METHOD_ANONYMOUS, ReviewAssignment::SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS])) {
                        $anonymousAuthor = false;
                    }
                }
                if ($eventLog->getData('username') && $anonymousAuthor) {
                    $eventLog->setData('username', __('editor.review.anonymousReviewer'));
                    $filenames = $eventLog->getData('filename');
                    $eventLog->setData('filename', array_map(function (string $value) {
                        return '';
                    }, $filenames));
                }
            }
        }

        $params = [];
        foreach ($eventLog->getAllData() as $key => $data) {
            if (!is_array($data)) {
                $params[$key] = $eventLog->getData($key);
                continue;
            }

            $params[$key] = $eventLog->getData($key, $locale);
        }

        return __($message, $params, $locale);
    }

    /**
     * Return the full name of the user.
     */
    public function getUserFullName(): string
    {
        $userFullName = & $this->getData('userFullName');
        if (!isset($userFullName) && $this->getUserId()) {
            $userFullName = Repo::user()->get($this->getUserId(), true)->getFullName();
        }

        return $userFullName ?: '';
    }

    /**
     * Return the email address of the user.
     */
    public function getUserEmail(): string
    {
        $userEmail = $this->getData('userEmail');

        if (!isset($userEmail)) {
            $userEmail = Repo::user()->get($this->getUserId(), true)->getEmail();
        }

        return $userEmail ?: '';
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\log\event\EventLogEntry', '\EventLogEntry');
    define('SUBMISSION_LOG_NOTE_POSTED', EventLogEntry::SUBMISSION_LOG_NOTE_POSTED);
    define('SUBMISSION_LOG_MESSAGE_SENT', EventLogEntry::SUBMISSION_LOG_MESSAGE_SENT);
}
