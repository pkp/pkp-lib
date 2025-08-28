<?php

/**
 * @file classes/task/CheckEmail.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CheckEmail
 *
 * @ingroup tasks
 *
 * @brief Class to check a configured email inbox for activity.
 */

namespace PKP\task;

use APP\facades\Repo;
use EmailReplyParser\Parser\EmailParser;
use PKP\config\Config;
use PKP\core\Core;
use PKP\note\Note;
use PKP\scheduledTask\ScheduledTask;
use Pop\Mail\Client\Imap;
use Sabre\VObject;

class CheckEmail extends ScheduledTask
{
    /**
     * @copydoc ScheduledTask::getName()
     */
    public function getName(): string
    {
        return __('admin.scheduledTask.checkEmail');
    }

    /**
     * @copydoc ScheduledTask::executeActions()
     */
    public function executeActions(): bool
    {
        if (($imapHost = Config::getVar('email', 'imap_host')) == null ||
            ($imapUsername = Config::getVar('email', 'imap_username')) == null ||
            ($imapPassword = Config::getVar('email', 'imap_password')) == null
        ) {
            error_log('IMAP inbox not configured; skipping email check.');
            return false;
        }
        error_log('Beginning email check task...');

        $imap = new Imap($imapHost, Config::getVar('email', 'imap_port', 993));
        $imap->setUsername($imapUsername)
            ->setPassword($imapPassword);

        $imap->setFolder('INBOX');
        $imap->open('/ssl');

        // Get all messages sorted by date, oldest first
        foreach ($imap->getMessageIdsBy(SORTDATE, true, SE_UID, 'UNDELETED') as $messageId) {
            error_log("Checking email ID {$messageId}");
            $headers = $imap->getMessageHeadersById($messageId);
            $messageParts = null;
            if (
                $this->handleDiscussionResponse($imap, $messageId, $headers, $messageParts) ||
                $this->handleReviewConfirmation($imap, $messageId, $headers, $messageParts)
            ) {
                $imap->deleteMessage($messageId);
            } else {
                error_log("Unhandled email ID {$messageId}");
            }
        }

        error_log('Completed email check task.');
        return true;
    }

    protected function handleReviewConfirmation(Imap $imap, string $messageId, array $headers, array &$messageParts = null): bool
    {
        $parts ??= $imap->getMessageParts($messageId);

        $candidatePart = null;
        foreach ($parts as $part) {
            if (strpos($part->headers['Content-type'], 'text/calendar') !== false) {
                $candidatePart = $part;
                break;
            }
        }
        // Ensure that a candidate text/calendar response was identified.
        if (!$candidatePart) {
            return false;
        }

        $vCalendar = VObject\Reader::read($candidatePart->content);

        // Identify the review assignment attached to this activity
        $reviewAssignment = Repo::reviewAssignment()->getCollector()->filterByMessageId($vCalendar->VEVENT->UID)->getMany()->first();
        if (!$reviewAssignment || $reviewAssignment->getDateConfirmed()) {
            return false;
        }

        switch ($partstat = $vCalendar->VEVENT->attendee['PARTSTAT']) {
            case 'ACCEPTED':
                // FIXME: Logging; email notification
                $reviewAssignment->setDateConfirmed(Core::getCurrentDate());
                Repo::reviewAssignment()->edit($reviewAssignment, ['dateConfirmed' => $reviewAssignment->getDateConfirmed()]);
                return true;
            case 'DECLINED':
                // FIXME: Logging; email notification
                $reviewAssignment->setDateConfirmed(Core::getCurrentDate());
                $reviewAssignment->setDeclined(1);
                Repo::reviewAssignment()->edit($reviewAssignment, ['dateConfirmed' => $reviewAssignment->getDateConfirmed(), 'declined' => $reviewAssignment->getDeclined()]);
                return true;
            case 'TENTATIVE':
                // We do nothing special with tentative acceptance at the moment.
                return true;
        }
        return false;

        error_log("Unhandled PARTSTAT of {$partstat} when processing email ID {$messageId}.");
        return false;
    }

    protected function handleDiscussionResponse(Imap $imap, string $messageId, array $headers, arram &$messageParts = null): bool
    {
        // Identify the note the email is in response to (by message ID)
        if (!isset($headers['in_reply_to'])) {
            return false;
        }

        // See if there is a note with a message ID corresponding to this message's in-reply-to.
        $note = Note::withMessageId(trim($headers['in_reply_to'], '<>'))->first();
        if (!$note) {
            return false;
        }

        // Identify the user who wrote the email
        // FIXME: this is bad, surely there's already something in the codebase to extract the address
        // from the fromaddress header (which may contain a fancy name)!
        $emails = array_filter(array_map(fn ($t) => filter_var($t, FILTER_VALIDATE_EMAIL), preg_split('~(\s+|<|>)+~', $headers['fromaddress'])));
        $email = array_pop($emails);
        if (empty($email)) {
            return false;
        }
        $user = Repo::user()->getByEmail($email);
        if (!$user) {
            return false;
        }

        // Find the text in the email message
        $parts ??= $imap->getMessageParts($messageId);
        $candidatePart = null;
        foreach ($parts as $part) {
            if (strpos($part->type, 'text/plain') !== false) {
                $candidatePart = $part;
                break;
            }
            if ($candidatePart) {
                break;
            }
        }
        if (!$candidatePart && count($parts) == 1) {
            $candidatePart = $parts[0];
        }
        if (!$candidatePart) {
            return false;
        }

        // Parse out the new text, removing quoted content
        $email = (new EmailParser())->parse($candidatePart->content);
        $newText = $email->getVisibleText();

        // We have successfully located a user, note, and text content. Add it to the DB.
        $newNote = Note::create([
            'assocType' => $note->assocType,
            'assocId' => $note->assocId,
            'userId' => $user->getId(),
            'title' => $headers['subject'],
            'contents' => $newText,
            'messageId' => Note::generateMessageId(),
        ]);

        error_log('Parsed an email response by ' . $user->getEmail() . ' to note ID ' . $note->id);
        return true;
    }
}
