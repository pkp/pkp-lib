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
use PKP\db\DAORegistry;
use PKP\scheduledTask\ScheduledTask;

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

        $imap = new \Pop\Mail\Client\Imap($imapHost, Config::getVar('email', 'imap_port', 993));
        $imap->setUsername($imapUsername)
            ->setPassword($imapPassword);

        $imap->setFolder('INBOX');
        $imap->open('/ssl');

        // Get all messages sorted by date, oldest first
        foreach ($imap->getMessageIdsBy(SORTDATE) as $messageId) {
            error_log("Checking email ID {$messageId}");
            $headers = $imap->getMessageHeadersById($messageId);

            // Identify the note the email is in response to (by message ID)
            if (!isset($headers['in_reply_to'])) {
                continue;
            }
            $noteDao = DAORegistry::getDAO('NoteDAO');
            $note = $noteDao->getByMessageId(trim($headers['in_reply_to'], '<>'));
            if (!$note) {
                continue;
            }

            // Identify the user who wrote the email
            // FIXME: this is bad, surely there's already something in the codebase to extract the address
            // from the fromaddress header (which may contain a fancy name)!
            $emails = array_filter(array_map(fn ($t) => filter_var($t, FILTER_VALIDATE_EMAIL), preg_split('~(\s+|<|>)+~', $headers['fromaddress'])));
            $email = array_pop($emails);
            if (empty($email)) {
                continue;
            }
            $user = Repo::user()->getByEmail($email);
            if (!$user) {
                continue;
            } // Could not look up user by email

            // Find the text in the email message
            $parts = $imap->getMessageParts($messageId);
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
                continue;
            }

            // Parse out the new text, removing quoted content
            $email = (new EmailParser())->parse($candidatePart->content);
            $newText = $email->getVisibleText();

            // We have successfully located a user, note, and text content. Add it to the DB.
            $newNote = $noteDao->newDataObject();
            $newNote->setAssocType($note->assocType);
            $newNote->setAssocId($note->assocId);
            $newNote->setUserId($user->getId());
            $newNote->setTitle($headers['subject']);
            $newNote->setContents($newText);
            $newNote->assignMessageId();
            $noteDao->insertObject($newNote);

            error_log('Parsed an email response by ' . $user->getEmail() . ' to note ID ' . $note->id);
            $imap->deleteMessage($messageId);
        }

        error_log('Completed email check task.');
        return true;
    }
}
