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

use APP\submission\Submission;
use Illuminate\Support\Facades\Mail;
use PKP\core\Core;
use PKP\facades\Locale;
use PKP\plugins\Hook;
use PKP\mail\Mailable;
use PKP\user\User;
use Carbon\Carbon;

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


        Hook::call('EmailLogDAO::build', [&$entry, &$row]);

        return $entry;
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

    /**
     * Create a log entry from data in a Mailable class
     *
     * @param int $eventType One of the SubmissionEmailLogEntry::SUBMISSION_EMAIL_* constants
     *
     * @return int The new log entry id
     */
    public function logMailable(int $eventType, Mailable $mailable, Submission $submission, ?User $sender = null): int
    {
        $clonedMailable = clone $mailable;
        $clonedMailable->removeFooter();

        $this->model->eventType = $eventType;
        $this->model->assocId = $submission->getId();
        $this->model->dateSent = Core::getCurrentDate();
        $this->model->senderId = $sender ? $sender->getId() : null;
        $this->model->from = $this->getContactString($clonedMailable->from);
        $this->model->recipients = $this->getContactString($clonedMailable->to);
        $this->model->css = $this->getContactString($clonedMailable->cc);
        $this->model->bccs = $this->getContactString($clonedMailable->bcc);
        $this->model->body = $clonedMailable->render();

        $this->model->subject = Mail::compileParams(
            $clonedMailable->subject,
            $clonedMailable->getData(Locale::getLocale())
        );

        $this->model->save();

        return $this->model->id;
    }
}
