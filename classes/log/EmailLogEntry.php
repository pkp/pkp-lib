<?php

/**
 * @file classes/log/EmailLogEntry.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EmailLogEntry
 *
 * @ingroup log
 *
 * @see EmailLogDAO
 *
 * @brief Describes an entry in the email log.
 */

namespace PKP\log;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

use APP\facades\Repo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class EmailLogEntry extends Model
{
    protected $table = 'email_log';
    protected $primaryKey = 'log_id';
    public $timestamps = false;

    protected $fillable = [
        'assocType',
        'assocId',
        'senderId',
        'dateSent',
        'eventType',
        'from',
        'recipients',
        'ccs',
        'bccs',
        'subject',
        'body',
    ];

    private $_senderFullName = '';
    private $_senderEmail = '';

    /**
     * The maximum length for the email subject.
     *
     * This value should match the length of the `subject` column in the `email_log` table, defined in LogMigration.php.
     */
    private const MAX_SUBJECT_LENGTH = 255;

    protected static function booted(): void
    {
        static::creating(function (EmailLogEntry $entry) {
            $subject = $entry->subject;
            // Subtract 3 to compensate for the '...' that gets added to the end of the string.
            $entry->subject = Str::limit($subject, self::MAX_SUBJECT_LENGTH - 3);
        });
    }

    //
    // Accessors / Mutators
    //
    protected function id(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes[$this->primaryKey] ?? null,
            set: fn($value) => [$this->primaryKey => $value],
        );
    }

    protected function senderId(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes['sender_id'],
            set: fn($value) => ['sender_id' => $value]
        );
    }

    protected function dateSent(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes['date_sent'],
            set: fn($value) => ['date_sent' => $value]
        );
    }

    protected function eventType(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes['event_type'],
            set: fn($value) => ['event_type' => $value]
        );
    }

    protected function assocType(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes['assoc_type'],
            set: fn($value) => ['assoc_type' => $value]
        );
    }

    protected function assocId(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes['assoc_id'],
            set: fn($value) => ['assoc_id' => $value]
        );
    }
    
    protected function from(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes['from_address'],
            set: fn($value) => ['from_address' => $value]
        );
    }


    protected function recipients(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes['recipients'],
            set: fn($value) => ['recipients' => $value]
        );
    }

    protected function ccs(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes['cc_recipients'],
            set: fn($value) => ['cc_recipients' => $value]
        );
    }

    protected function bccs(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes['bcc_recipients'],
            set: fn($value) => ['bcc_recipients' => $value]
        );
    }

    protected function subject(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes['subject'],
            set: fn($value) => ['subject' => $value]
        );
    }

    protected function body(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes['body'],
            set: fn($value) => ['body' => $value],
        );
    }

    /**
     * Return the full name of the sender (not necessarily the same as the from address).
     *
     * @return string
     */
    public function getSenderFullName()
    {

        if ($this->_senderFullName) {
            return $this->_senderFullName;
        }

        $sender = $this->senderId
            ? Repo::user()->get($this->senderId, true)
            : null;

        $this->_senderFullName = $sender->getFullName();

        return $this->_senderFullName ? $this->_senderFullName : '';
    }

    /**
     * Return the email address of sender.
     *
     * @return string
     */
    public function getSenderEmail()
    {

        if (!isset($this->_senderEmail)) {
            $this->_senderEmail = Repo::user()->get($this->getSenderId(), true)->getEmail();
        }

        return ($this->_senderEmail ?: '');
    }


    /**
     * Returns the subject of the message with a prefix explaining the event type
     *
     * @return string Prefixed subject
     */
    public function getPrefixedSubject()
    {
        return __('submission.event.subjectPrefix') . ' ' . $this->subject;
    }

    //
    // Scopes
    //
    public function scopeWithSubmissionId(Builder $query, int $submissionId = null): Builder
    {
        return $query->where('assoc_id', (int)$submissionId);
    }

    public function scopeWithSenderId(Builder $query, $senderId): Builder
    {
        return $query->when($senderId !== null, function ($query) use ($senderId) {
            return $query->where('sender_id', $senderId);
        });
    }

    public function scopeWithEventType(Builder $query, $eventType): Builder
    {
        return $query->when($eventType !== null, function ($query) use ($eventType) {
            return $query->where('event_type', $eventType);
        });
    }

    public function scopeWithAssocType(Builder $query, $assocType): Builder
    {
        return $query->when($assocType !== null, function ($query) use ($assocType) {
            return $query->where('assoc_type', $assocType);
        });
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\log\EmailLogEntry', '\EmailLogEntry');
}
