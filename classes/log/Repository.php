<?php
/**
 * @file classes/log/Repository.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EmailLogRepository
 *
 * @ingroup log
 *
 * @see EmailLogEntry
 *
 * @brief Operations for retrieving and modifying EmailLogEntry objects.
 */

namespace PKP\log;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Support\Facades\Mail;
use PKP\core\Core;
use PKP\facades\Locale;
use PKP\log\core\EmailLogEventType;
use PKP\plugins\Hook;
use PKP\mail\Mailable;
use PKP\user\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Repository
{

    private EmailLogEntry $model;

    public function __construct(EmailLogEntry $model)
    {
        $this->model = $model;
    }


    /**
     * Function to return an EmailLogEntry object.
     *
     * @param array $row
     *
     * @return EmailLogEntry
     *
     * @hook EmailLogDAO::build [[&$entry, &$row]]
     */
    public function build($row)
    {
        $entry = new EmailLogEntry([
            'assocType' => $row['assoc_type'],
            'assocId' => $row['assoc_id'],
            'senderId' => $row['sender_id'],
            'dateSent' => Carbon::parse($row['date_sent'])->toIso8601String(),
            'eventType' => $row['event_type'],
            'from' => $row['from_address'],
            'recipients' => $row['recipients'],
            'ccs' => $row['cc_recipients'],
            'bccs' => $row['bcc_recipients'],
            'subject' => $row['subject'],
            'body' => $row['body']
        ]);


        return $entry;
    }

    /**
     * Get the from or to data as a string
     *
     * @param array $addressees Expects Mailable::$to or Mailable::$from
     */
    private function getContactString(array $addressees): string
    {
        $contactStrings = [];
        foreach ($addressees as $addressee) {
            $contactStrings[] = isset($addressee['name'])
                ? '"' . $addressee['name'] . '" <' . $addressee['address'] . '>'
                : $addressee['address'];
        }
        return join(', ', $contactStrings);
    }


    /**
     * Stores the correspondent user ids of the all recipient emails.
     *
     * @param EmailLogEntry $entry
     */
    private function insertLogUserIds(EmailLogEntry $entry)
    {
        $recipients = $entry->recipients;

        // We can use a simple regex to get emails, since we don't want to validate it.
        $pattern = '/(?<=\<)[^\>]*(?=\>)/';
        preg_match_all($pattern, $recipients, $matches);
        if (!isset($matches[0])) {
            return;
        }

        foreach ($matches[0] as $emailAddress) {
            $user = Repo::user()->getByEmail($emailAddress, true);
            if ($user instanceof \PKP\user\User) {
                // We use replace here to avoid inserting duplicated entries
                // in table (sometimes the recipients can have the same email twice).
                DB::table('email_log_users')->updateOrInsert(
                    ['email_log_id' => $entry->id, 'user_id' => $user->getId()]
                );
            }
        }
    }

    /**
     * Delete all log entries for an object.
     *
     * @param int $assocType
     * @param int $assocId
     * @return int The number of affected rows.
     */
    public function deleteByAssoc(int $assocType, int $assocId): int
    {
        return $this->model
            ->newQuery()
            ->where('assoc_type', (int)$assocType)
            ->where('assoc_id', (int)$assocId)
            ->delete();
    }

    /**
     * Transfer all log and log users entries to another user.
     *
     * @param int $oldUserId
     * @param int $newUserId
     */
    public function changeUser($oldUserId, $newUserId)
    {
        return [
            DB::update(
                'UPDATE email_log SET sender_id = ? WHERE sender_id = ?',
                [(int) $newUserId, (int) $oldUserId]
            ),
            DB::update(
                'UPDATE email_log_users
                SET user_id = ?
                WHERE user_id = ? AND email_log_id NOT IN (SELECT t1.email_log_id
                    FROM (SELECT email_log_id FROM email_log_users WHERE user_id = ?) AS t1
                    INNER JOIN
                    (SELECT email_log_id FROM email_log_users WHERE user_id = ?) AS t2
                    ON t1.email_log_id = t2.email_log_id);',
                [(int)$newUserId, (int)$oldUserId, (int)$newUserId, (int)$oldUserId]
            )
        ];
    }

    /**
     * Create a log entry for a submission from data in a Mailable class
     *
     * @param EmailLogEventType $eventType Type of email event to log
     * @param Mailable $mailable
     * @param Submission $submission
     * @param ?User $sender
     * @return int The new log entry id
     */
    function logMailable(EmailLogEventType $eventType, Mailable $mailable, Submission $submission, ?User $sender = null): int
    {
        $clonedMailable = clone $mailable;
        $clonedMailable->removeFooter();

        $this->model->eventType = $eventType->value;
        $this->model->assocId = $submission->getId();
        $this->model->dateSent = Core::getCurrentDate();
        $this->model->senderId = $sender ? $sender->getId() : null;
        $this->model->fromAddress = $this->getContactString($clonedMailable->from);
        $this->model->recipients = $this->getContactString($clonedMailable->to);
        $this->model->ccRecipients = $this->getContactString($clonedMailable->cc);
        $this->model->bccRecipients = $this->getContactString($clonedMailable->bcc);
        $this->model->body = $clonedMailable->render();
        $this->model->assocType = Application::ASSOC_TYPE_SUBMISSION;
        $this->model->subject = Mail::compileParams(
            $clonedMailable->subject,
            $clonedMailable->getData(Locale::getLocale())
        );

        $this->model->save();
        $this->insertLogUserIds( $this->model);

        return $this->model->id;
    }

    /**
     * Get email log entries by assoc ID, event type and assoc type
     *
     * @param int $assocId
     * @param EmailLogEventType $eventType Type of email event
     * @param int $assocType
     * @param ?int $userId optional Return only emails sent to this user.
     */
    function getByEventType(int $assocId, EmailLogEventType $eventType, int $assocType, ?int $userId = null)
    {
        $query = $this->model->newQuery();

        if ($userId) {
            $query->leftJoin('email_log_users as u', 'email_log.log_id', '=', 'u.email_log_id');
        }

        $query
            ->where('assoc_type', $assocType)
            ->where('assoc_id', $assocId)
            ->where('event_type', $eventType->value)
            ->when($userId, function ($query) use ($userId) {
                $query->where('u.user_id', $userId);
            })->select('email_log.*');

        return $query->get(); // Counted in submissionEmails.tpl
    }
}
