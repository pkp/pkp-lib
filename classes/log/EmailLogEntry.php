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

    //
    // Get/set methods
    //
    protected function id(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes[$this->primaryKey] ?? null,
            set: fn($value) => [$this->primaryKey => $value],
        );
    }

    /**
     * Get user ID of sender.
     *
     * @return int
     */
    public function getSenderId()
    {
        return $this->getData('senderId');
    }

    /**
     * Set user ID of sender.
     *
     * @param int $senderId
     */
    public function setSenderId($senderId)
    {
        $this->setData('senderId', $senderId);
    }

    protected function senderId(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes['sender_id'],
            set: fn($value) => ['sender_id' => $value]
        );
    }

    /**
     * Get date email was sent.
     *
     * @return string
     */
    public function getDateSent()
    {
        return $this->getData('dateSent');
    }

    /**
     * Set date email was sent.
     *
     * @param string $dateSent
     */
    public function setDateSent($dateSent)
    {
        $this->setData('dateSent', $dateSent);
    }

    protected function dateSent(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes['date_sent'],
            set: fn($value) => ['date_sent' => $value]
        );
    }

    /**
     * Get event type.
     *
     * @return int
     */
    public function getEventType()
    {
        return $this->getData('eventType');
    }

    /**
     * Set event type.
     *
     * @param int $eventType
     */
    public function setEventType($eventType)
    {
        $this->setData('eventType', $eventType);
    }

    protected function eventType(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes['event_type'],
            set: fn($value) => ['event_type' => $value]
        );
    }

    /**
     * Get associated type.
     *
     * @return int
     */
    public function getAssocType()
    {
        return $this->getData('assocType');
    }

    /**
     * Set associated type.
     *
     * @param int $assocType
     */
    public function setAssocType($assocType)
    {
        $this->setData('assocType', $assocType);
    }

    protected function assocType(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes['assoc_type'],
            set: fn($value) => ['assoc_type' => $value]
        );
    }



    /**
     * Get associated ID.
     *
     * @return int
     */
    public function getAssocId()
    {
        return $this->getData('assocId');
    }

    /**
     * Set associated ID.
     *
     * @param int $assocId
     */
    public function setAssocId($assocId)
    {
        $this->setData('assocId', $assocId);
    }

    protected function assocId(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes['assoc_id'],
            set: fn($value) => ['assoc_id' => $value]
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

        $sender = $this->getSenderId()
            ? Repo::user()->get($this->getSenderId(), true)
            : null;

        $this->_senderFullName = $sender->getFullName();

        return $sender ? $sender->getFullName() : '';
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


    //
    // Email data
    //

    public function getFrom()
    {
        return $this->getData('from');
    }

    public function setFrom($from)
    {
        $this->setData('from', $from);
    }

    protected function from(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes['from_address'],
            set: fn($value) => ['from_address' => $value]
        );
    }


    public function getRecipients()
    {
        return $this->getData('recipients');
    }

    public function setRecipients($recipients)
    {
        $this->setData('recipients', $recipients);
    }

    protected function recipients(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes['recipients'],
            set: fn($value) => ['recipients' => $value]
        );
    }

    public function getCcs()
    {
        return $this->getData('ccs');
    }

    public function setCcs($ccs)
    {
        $this->setData('ccs', $ccs);
    }

    protected function ccs(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes['cc_recipients'],
            set: fn($value) => ['cc_recipients' => $value]
        );
    }

    public function getBccs()
    {
        return $this->getData('bccs');
    }

    public function setBccs($bccs)
    {
        $this->setData('bccs', $bccs);
    }

    protected function bccs(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes['bcc_recipients'],
            set: fn($value) => ['bcc_recipients' => $value]
        );
    }

    public function getSubject()
    {
        return $this->getData('subject');
    }

    public function setSubject($subject)
    {
        $this->setData('subject', $subject);
    }

    protected function subject(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes['subject'],
            set: fn($value) => ['subject' => $value]
        );
    }

    public function getBody()
    {
        return $this->getData('body');
    }

    public function setBody($body)
    {
        $this->setData('body', $body);
    }

    protected function body(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes['body'],
            set: fn($value) => ['body' => $value],
        );
    }


    /**
     * Returns the subject of the message with a prefix explaining the event type
     *
     * @return string Prefixed subject
     */
    public function getPrefixedSubject()
    {
        return __('submission.event.subjectPrefix') . ' ' . $this->getSubject();
    }

    // Scopes
    public function scopeWithSubmissionIds(Builder $query, ?array $submissionIds): Builder
    {
        return $query->when($submissionIds !== null, function ($query) use ($submissionIds) {
            return $query->whereIn('submission_id', $submissionIds);
        });
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
