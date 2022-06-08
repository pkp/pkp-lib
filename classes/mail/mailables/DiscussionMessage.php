<?php

/**
 * @file classes/mail/mailables/DiscussionMessage.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DiscussionMessage
 * @ingroup mail_mailables
 *
 * @brief Email sent when a message is added to a query
 */

namespace PKP\mail\mailables;

use PKP\context\Context;
use PKP\mail\traits\Configurable;
use PKP\mail\Mailable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;
use PKP\submission\PKPSubmission;

class DiscussionMessage extends Mailable
{
    use Recipient;
    use Sender;
    use Configurable;

    protected static ?string $name = 'mailable.discussionMessage.name';
    protected static ?string $description = 'mailable.discussionMessage.description';
    protected static ?string $emailTemplateKey = 'NOTIFICATION';
    protected static array $groupIds = [
        self::GROUP_SUBMISSION,
        self::GROUP_REVIEW,
        self::GROUP_COPYEDITING,
        self::GROUP_PRODUCTION
    ];

    public function __construct(Context $context, PKPSubmission $submission)
    {
        parent::__construct(func_get_args());
    }
}
