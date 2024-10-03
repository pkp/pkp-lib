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
 * @brief Describes an entry in the email log.
 */

namespace PKP\log;

use APP\facades\Repo;
use Eloquence\Behaviours\HasCamelCasing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use PKP\log\core\EmailLogEventType;

class EmailLogEntry extends Model
{
    use HasCamelCasing;

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
        'subject',
        'body',
        'bccRecipients',
        'ccRecipients',
        'fromAddress'
    ];

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
            get: fn ($value, $attributes) => $attributes[$this->primaryKey] ?? null,
            set: fn ($value) => [$this->primaryKey => $value],
        );
    }


    /**
     * Return the full name of the sender (not necessarily the same as the from address).
     */
    protected function senderFullName(): Attribute
    {
        return Attribute::make(
            get: function () {
                $sender = $this->senderId
                    ? Repo::user()->get($this->senderId, true)
                    : null;
                return $sender ? $sender->getFullName() : '';
            },
        )->shouldCache();
    }

    /**
     * Return the email address of sender.
     */
    protected function senderEmail(): Attribute
    {
        return Attribute::make(
            get: function () {
                $email = Repo::user()->get($this->senderId, true)->getEmail();

                return $email ?: '';
            },
        )->shouldCache();
    }


    /**
     * Returns the subject of the message with a prefix explaining the event type
     */
    protected function prefixedSubject(): Attribute
    {
        return Attribute::make(
            get: fn () => __('submission.event.subjectPrefix') . ' ' . $this->subject
        )->shouldCache();
    }


    // Scopes

    /**
     * Scope a query to only include email log entries with a specific assocId
     */
    public function scopeWithAssocId(Builder $query, int $assocId): Builder
    {
        return $query->where('assoc_id', $assocId);
    }

    public function scopeWithSenderId(Builder $query, int $senderId): Builder
    {
        return $query->where('sender_id', $senderId);
    }


    /**
     * Scope a query to only include email log entries with specific evenTypes.
     *
     * @param EmailLogEventType[] $eventTypes - eventTypes to filter by
     */
    public function scopeWithEventTypes(Builder $query, array $eventTypes): Builder
    {
        return $query->when(!empty($eventTypes), function ($query) use ($eventTypes) {
            return $query->whereIn('event_type', array_map(fn ($type) => $type->value, $eventTypes));
        });
    }

    /**
     * Scope a query to only include email log entries with a specific assoc type
     */
    public function scopeWithAssocType(Builder $query, int $assocType): Builder
    {
        return $query->where('assoc_type', $assocType);
    }

    /**
     * Scope a query to only include email log entries for a specific email recipient
     */
    public function scopeWithRecipientId(Builder $query, int $recipientId): Builder
    {
        return $query->leftJoin('email_log_users as u', 'email_log.log_id', '=', 'u.email_log_id')
            ->where('user_id', $recipientId);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\log\EmailLogEntry', '\EmailLogEntry');
}
