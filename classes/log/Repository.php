<?php
/**
 * @file classes/log/Repository.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @ingroup log
 *
 * @brief Operations for retrieving and modifying EmailLogEntry objects.
 */

namespace PKP\log;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PKP\core\Core;
use PKP\facades\Locale;
use PKP\log\core\EmailLogEventType;
use PKP\log\core\maps\Schema;
use PKP\mail\Mailable;
use PKP\user\User;

class Repository
{
    // The name of the class to map this entity to its schema
    public string $schemaMap = Schema::class;

    public function __construct(private EmailLogEntry $model)
    {
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
     */
    private function insertLogUserIds(EmailLogEntry $entry): void
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
     * @return int The number of affected rows.
     */
    public function deleteByAssoc(int $assocType, int $assocId): int
    {
        return $this->model
            ->newQuery()
            ->where('assoc_type', $assocType)
            ->where('assoc_id', $assocId)
            ->delete();
    }

    /**
     * Transfer all log and log users entries to another user.
     */
    public function changeUser(int $oldUserId, int $newUserId)
    {
        return [
            $this->model
                ->newQuery()
                ->where('sender_id', $oldUserId)
                ->update(['sender_id' => $newUserId]),

            DB::table('email_log_users')
                ->where('user_id', $oldUserId)
                ->whereNotIn('email_log_id', function ($query) use ($newUserId, $oldUserId) {
                    $query->select('t1.email_log_id')
                        ->from(DB::table('email_log_users')->as('t1'))
                        ->join(DB::table('email_log_users')->as('t2'), 't1.email_log_id', '=', 't2.email_log_id')
                        ->where('t1.user_id', $newUserId)
                        ->where('t2.user_id', $oldUserId);
                })->update(['user_id' => $newUserId])
        ];
    }

    /**
     * Create a log entry for a submission from data in a Mailable class
     *
     * @return int The new log entry id
     */
    public function logMailable(EmailLogEventType $eventType, Mailable $mailable, Submission $submission, ?User $sender = null): int
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
        $this->insertLogUserIds($this->model);

        return $this->model->id;
    }

    /**
     * Get email log entries by assoc ID, event type and assoc type
     *
     * @param ?int $userId optional Return only emails sent to this user.
     */
    public function getByEventType(int $assocId, EmailLogEventType $eventType, int $assocType, ?int $userId = null)
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

    /***
     * Checks if a user is a recipient of a given email
     */
    public function isUserEmailRecipient(int $emailId, int $recipientId): bool
    {
        $query = $this->model->newQuery();
        $query->where('log_id', $emailId)->withRecipientId($recipientId);

        return !empty($query->first());
    }

    /**
     * Get an instance of the map class for mapping log entries to their schema
     */
    public function getSchemaMap(): Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }
}
