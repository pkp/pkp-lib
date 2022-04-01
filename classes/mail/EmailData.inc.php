<?php

/**
 * @file mail/EmailData.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EmailData
 * @ingroup mail
 *
 * @brief A class to hold data received from common request parameters. Used
 *   with the Composer UI component.
 */

namespace PKP\mail;

class EmailData
{
    /**
     * User IDs for the recipients of the email
     *
     * @var int[] $recipientss
     */
    public array $recipients = [];

    /**
     * The body of the email
     */
    public string $body;

    /**
     * The subject of the email
     */
    public string $subject;

    /**
     * The bcc recipients of this email
     */
    public array $bcc = [];

    /**
     * The cc recipients of this email
     */
    public array $cc = [];

    /**
     * Attachments for the email
     *
     * Each attachment is an array with id and name properties.
     * The id key must be one of the Mailable::ATTACHMENT_TEMPORARY_FILE
     * constants.
     *
     * Example:
     *
     * [
     *   ['temporaryFileId' => 1, 'name' => 'example.docx']
     *   ['submissionFileId' => 2, 'name' => 'other.pdf']
     * ]
     *
     * @param array[]
     */
    public array $attachments = [];

    /**
     * The locale of the email
     */
    public ?string $locale = null;

    /**
     * Instantiate an object from an assoc array of request data
     *
     * @param array $args [
     */
    public function __construct(array $args = [])
    {
        foreach ($args as $key => $value) {
            if (property_exists(EmailData::class, $key)) {
                $this->{$key} = $value;
            }
        }
    }
}
