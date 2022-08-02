<?php

/**
 * @file classes/log/EmailLogEntry.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EmailLogEntry
 * @ingroup log
 *
 * @see EmailLogDAO
 *
 * @brief Describes an entry in the email log.
 */

namespace PKP\log;

use APP\facades\Repo;

class EmailLogEntry extends \PKP\core\DataObject
{
    //
    // Get/set methods
    //

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

    /**
     * Get date email was sent.
     *
     * @return datestamp
     */
    public function getDateSent()
    {
        return $this->getData('dateSent');
    }

    /**
     * Set date email was sent.
     *
     * @param datestamp $dateSent
     */
    public function setDateSent($dateSent)
    {
        $this->setData('dateSent', $dateSent);
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

    /**
     * Return the full name of the sender (not necessarily the same as the from address).
     *
     * @return string
     */
    public function getSenderFullName()
    {
        $senderFullName = & $this->getData('senderFullName');

        if (!isset($senderFullName)) {
            $senderFullName = Repo::user()->get($this->getSenderId(), true)->getFullName();
        }

        return ($senderFullName ? $senderFullName : '');
    }

    /**
     * Return the email address of sender.
     *
     * @return string
     */
    public function getSenderEmail()
    {
        $senderEmail = & $this->getData('senderEmail');

        if (!isset($senderEmail)) {
            $senderEmail = Repo::user()->get($this->getSenderId(), true)->getEmail();
        }

        return ($senderEmail ? $senderEmail : '');
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

    public function getRecipients()
    {
        return $this->getData('recipients');
    }

    public function setRecipients($recipients)
    {
        $this->setData('recipients', $recipients);
    }

    public function getCcs()
    {
        return $this->getData('ccs');
    }

    public function setCcs($ccs)
    {
        $this->setData('ccs', $ccs);
    }

    public function getBccs()
    {
        return $this->getData('bccs');
    }

    public function setBccs($bccs)
    {
        $this->setData('bccs', $bccs);
    }

    public function getSubject()
    {
        return $this->getData('subject');
    }

    public function setSubject($subject)
    {
        $this->setData('subject', $subject);
    }

    public function getBody()
    {
        return $this->getData('body');
    }

    public function setBody($body)
    {
        $this->setData('body', $body);
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
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\log\EmailLogEntry', '\EmailLogEntry');
}
