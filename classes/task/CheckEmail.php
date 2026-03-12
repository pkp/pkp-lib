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

    private function dbg(string $msg): void
    {
        $line = '[' . date('Y-m-d H:i:s') . '] [CheckEmail] ' . $msg . PHP_EOL;
        // Always write to a temp file – ignores whatever scheduler does with output
        @file_put_contents(sys_get_temp_dir() . '/checkemail_debug.log', $line, FILE_APPEND);
    }

    
    private function normalizeMessageId(?string $id): ?string
    {
        if (!$id) {
            return null;
        }

        $id = trim($id);
        // remove wrapping < > only
        $id = preg_replace('/^\s*<(.+)>\s*$/', '$1', $id);
        return trim($id);
    }


    private function parseReferences(?string $referencesHeader): array
    {
        if (!$referencesHeader) {
            return [];
        }

        $parts = preg_split('/\s+/', trim($referencesHeader)) ?: [];
        $parts = array_values(array_filter($parts, fn($v) => $v !== ''));

        return array_values(array_filter(array_map(
            fn ($id) => $this->normalizeMessageId($id),
            $parts
        )));
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
            $this->dbg('IMAP inbox not configured; skipping email check.');
            return false;
        }

        $imap = new Imap($imapHost, Config::getVar('email', 'imap_port', 993));
        $imap->setUsername($imapUsername)
            ->setPassword($imapPassword);

        $imap->setFolder('INBOX');
        $imap->open('/ssl');

        // IMPORTANT: do not filter by TO/reply_to_address here
        $ids = $imap->getMessageIdsBy(SORTDATE, true, SE_UID, 'UNDELETED');

        $maxEmailsToProcess = (int) Config::getVar('email', 'check_email_max_messages', 50);
        if ($maxEmailsToProcess > 0 && count($ids) > $maxEmailsToProcess) {
            $ids = array_slice($ids, 0, $maxEmailsToProcess);
        }

        error_log('CheckEmail: processing ' . count($ids) . ' recent messages');

        foreach ($ids as $messageId) {
            try {
                $headers = $imap->getMessageHeadersById($messageId);
                $messageParts = null;

                if (
                    $this->handleDiscussionResponse($imap, $messageId, $headers, $messageParts) ||
                    $this->handleReviewConfirmation($imap, $messageId, $headers, $messageParts)
                ) {
                    error_log("CheckEmail: handled email ID {$messageId}, deleting");
                    $imap->deleteMessage($messageId);
                }
            } catch (\Throwable $e) {
                error_log("CheckEmail: exception for message {$messageId}: " . $e->getMessage());
            }
        }

        return true;
    }



    protected function handleReviewConfirmation(Imap $imap, string $messageId, array $headers, array &$messageParts = null): bool
    {
        $parts = $messageParts ?? $imap->getMessageParts($messageId);
        $messageParts = $parts;

        $candidatePart = null;
        foreach ($parts as $part) {
            $contentType = $part->headers['Content-type']
                ?? $part->headers['content-type']
                ?? $part->type
                ?? '';

            if (is_array($contentType)) {
                $contentType = reset($contentType);
            }
            if (strpos($contentType, 'text/calendar') !== false) {
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
        $uid = (string) $vCalendar->VEVENT->UID;

        $reviewAssignment = Repo::reviewAssignment()
            ->getCollector()
            ->filterByMessageId($uid)
            ->getMany()
            ->first();

        if (!$reviewAssignment || $reviewAssignment->getDateConfirmed()) {
            return false;
        }

        // Normalize PARTSTAT
        $partstat = strtoupper((string) $vCalendar->VEVENT->ATTENDEE['PARTSTAT']);

        switch ($partstat) {
            case 'ACCEPTED':
                // FIXME: Logging; email notification
                $reviewAssignment->setDateConfirmed(Core::getCurrentDate());
                Repo::reviewAssignment()->edit($reviewAssignment, [
                    'dateConfirmed' => $reviewAssignment->getDateConfirmed(),
                ]);
                return true;

            case 'DECLINED':
                // FIXME: Logging; email notification
                $reviewAssignment->setDateConfirmed(Core::getCurrentDate());
                $reviewAssignment->setDeclined(1);
                Repo::reviewAssignment()->edit($reviewAssignment, [
                    'dateConfirmed' => $reviewAssignment->getDateConfirmed(),
                    'declined' => $reviewAssignment->getDeclined(),
                ]);
                return true;

            case 'TENTATIVE':
                // We do nothing special with tentative acceptance at the moment.
                return true;
        }

        // Only reached for unknown / unsupported PARTSTAT
        error_log("Unhandled PARTSTAT of {$partstat} when processing email ID {$messageId}.");

        return false;
    }


    protected function handleDiscussionResponse(Imap $imap, string $messageId, array $headers, array &$messageParts = null): bool
    {
        // Normalize header keys once so we don't depend on exact casing/format
        $normalizedHeaders = [];
        foreach ($headers as $key => $value) {
            $normalizedHeaders[strtolower($key)] = $value;
        }

        // Helpful: see what headers we actually get
        error_log('CheckEmail: message ' . $messageId . ' header keys: ' . implode(',', array_keys($normalizedHeaders)));
        $hasInReplyTo = false;
        $hasReferences = false;
        foreach ($normalizedHeaders as $key => $value) {
            if (strpos($key, 'in-reply-to') !== false || strpos($key, 'in_reply_to') !== false) {
                $hasInReplyTo = true;
            }
            if ($key === 'references') {
                $hasReferences = true;
            }
        }
        if (!$hasInReplyTo && !$hasReferences) {
            return false;
        }
        /**
         * 1. Find message-id linkage headers and map to Note::messageId
         */
        $inReplyToHeader = null;
        $referencesHeader = null;

        foreach ($normalizedHeaders as $key => $value) {
            if ($inReplyToHeader === null && (strpos($key, 'in-reply-to') !== false || strpos($key, 'in_reply_to') !== false)) {
                $inReplyToHeader = is_array($value) ? reset($value) : $value;
            }
            if ($referencesHeader === null && $key === 'references') {
                $referencesHeader = is_array($value) ? reset($value) : $value;
            }
        }

        $normalizedInReplyTo = $this->normalizeMessageId($inReplyToHeader);
        $referenceIds = $this->parseReferences($referencesHeader);
        $firstRef = $referenceIds[0] ?? null;
        $lastRef = !empty($referenceIds) ? $referenceIds[count($referenceIds) - 1] : null;

        $this->dbg('message ' . $messageId . ' raw In-Reply-To=' . var_export($inReplyToHeader, true));
        $this->dbg('message ' . $messageId . ' normalized In-Reply-To=' . var_export($normalizedInReplyTo, true));
        $this->dbg('message ' . $messageId . ' raw References=' . var_export($referencesHeader, true));
        $this->dbg('message ' . $messageId . ' normalized References first=' . var_export($firstRef, true) . ' last=' . var_export($lastRef, true));

        $lookupCandidates = array_values(array_unique(array_filter(array_merge(
            [$normalizedInReplyTo],
            array_reverse($referenceIds), // try newest ref first
            [$firstRef]
        ))));

        $this->dbg('message ' . $messageId . ' lookup field=messageId candidates=' . json_encode($lookupCandidates));

        if (empty($lookupCandidates)) {
            $this->dbg('message ' . $messageId . ' no usable message-id linkage headers');
            return false;
        }

        $note = null;
        $matchedCandidate = null;
        foreach ($lookupCandidates as $candidate) {
            $candidate = trim((string) $candidate, " \t\n\r\0\x0B<>\"'");
            if ($candidate === '') {
                continue;
            }

            $this->dbg('message ' . $messageId . ' trying Note::withMessageId(' . $candidate . ')');
            $note = Note::withMessageId($candidate)->first();

            if (!$note) {
                $note = Note::withMessageId('<' . $candidate . '>')->first();
            }

            if ($note) {
                $matchedCandidate = $candidate;
                break;
            }
        }

        if (!$note) {
            $this->dbg('message ' . $messageId . ' no Note found for candidates=' . json_encode($lookupCandidates));
            return false;
        }

        $this->dbg('message ' . $messageId . ' note found noteId=' . $note->id . ' matchedCandidate=' . $matchedCandidate);


        /**
         * 2. Identify the user from sender headers (domain-agnostic)
         * Prefer from/sender first, then reply-to fallbacks.
         */
        $senderHeaderValues = [];
        foreach (['fromaddress', 'from', 'senderaddress', 'sender', 'reply_toaddress', 'reply_to'] as $key) {
            if (!array_key_exists($key, $normalizedHeaders)) {
                continue;
            }
            $value = $normalizedHeaders[$key];
            if (is_array($value)) {
                foreach ($value as $v) {
                    if ($v !== null && $v !== '') {
                        $senderHeaderValues[] = (string) $v;
                    }
                }
            } else {
                if ($value !== null && $value !== '') {
                    $senderHeaderValues[] = (string) $value;
                }
            }
        }

        $senderHeaderValues = array_values(array_unique(array_filter($senderHeaderValues)));

        if (empty($senderHeaderValues)) {
            $this->dbg('message ' . $messageId . ' FAIL no sender headers present');
            return false;
        }

        // Extract candidate emails from all sender-ish headers
        $emailCandidates = [];
        foreach ($senderHeaderValues as $rawHeader) {
            if (preg_match_all('/<([^>]+)>/', $rawHeader, $matches)) {
                foreach ($matches[1] as $m) {
                    $e = filter_var(trim($m), FILTER_VALIDATE_EMAIL);
                    if ($e) {
                        $emailCandidates[] = strtolower($e);
                    }
                }
            }

            $bare = filter_var(trim($rawHeader), FILTER_VALIDATE_EMAIL);
            if ($bare) {
                $emailCandidates[] = strtolower($bare);
            }
        }

        $emailCandidates = array_values(array_unique($emailCandidates));

        $this->dbg('message ' . $messageId . ' sender headers=' . json_encode($senderHeaderValues));
        $this->dbg('message ' . $messageId . ' email candidates=' . json_encode($emailCandidates));

        if (empty($emailCandidates)) {
            $this->dbg('message ' . $messageId . ' FAIL could not parse sender email from headers');
            return false;
        }

        $user = null;
        $matchedEmail = null;
        foreach ($emailCandidates as $candidateEmail) {
            $user = Repo::user()->getByEmail($candidateEmail);
            if ($user) {
                $matchedEmail = $candidateEmail;
                break;
            }
        }

        if (!$user) {
            $this->dbg('message ' . $messageId . ' FAIL no user found for candidates=' . json_encode($emailCandidates));
            return false;
        }

        $this->dbg('message ' . $messageId . ' matched userId=' . $user->getId() . ' email=' . $matchedEmail);

        /**
         * 3. Find a text/plain part for the reply body
         */
        $parts = $messageParts ?? $imap->getMessageParts($messageId);
        $messageParts = $parts;

        $candidatePart = null;
        foreach ($parts as $part) {
            $contentType = $part->headers['Content-type'] ?? $part->headers['content-type'] ?? $part->type ?? '';
            if (is_array($contentType)) {
                $contentType = reset($contentType);
            }

            if (strpos($contentType, 'text/plain') !== false) {
                $candidatePart = $part;
                break;
            }
        }

        if (!$candidatePart && count($parts) === 1) {
            $candidatePart = $parts[0];
        }
        if (!$candidatePart) {
            error_log('CheckEmail: no text/plain part for message ' . $messageId);
            return false;
        }

        /**
         * 4. Parse out the new text (strip quoted content)
         */
        $parsedEmail = (new EmailParser())->parse($candidatePart->content);
        $newText = $parsedEmail->getVisibleText();
        if ($newText === '') {
            error_log('CheckEmail: visibleText empty for message ' . $messageId);
            return false;
        }



        $createdNote = Note::create([
            'assocType' => $note->assocType,
            'assocId'   => $note->assocId,
            'userId'    => $user->getId(),
            'contents'  => $newText,
            'messageId' => Note::generateMessageId(),
        ]);

        if ($createdNote) {
            $this->dbg('created reply noteId=' . $createdNote->id . ' assocId=' . $note->assocId . ' from=' . $user->getEmail() . ' messageId=' . $createdNote->messageId);
        } else {
            $this->dbg('reply note create returned empty for assocId=' . $note->assocId . ' from=' . $user->getEmail());
        }


        error_log('CheckEmail: created reply Note for assocId=' . $note->assocId . ' from=' . $user->getEmail());
        return true;
    }

}
