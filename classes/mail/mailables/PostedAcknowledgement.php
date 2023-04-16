<?php

/**
 * @file classes/mail/mailables/PostedAcknowledgement.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PostedAcknowledgement
 *
 * @ingroup mail_mailables
 *
 * @brief Email sent to submitting author when their preprint is posted.
 */

namespace APP\mail\mailables;

use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use PKP\security\Role;

class PostedAcknowledgement extends Mailable
{
    use Configurable;
    use Recipient;

    protected static ?string $name = 'mailable.postedAck.name';
    protected static ?string $description = 'mailable.postedAck.description';
    protected static ?string $emailTemplateKey = 'POSTED_ACK';
    protected static array $groupIds = [self::GROUP_PRODUCTION];
    protected static array $fromRoleIds = [self::FROM_SYSTEM];
    protected static array $toRoleIds = [Role::ROLE_ID_AUTHOR];

    public function __construct(Context $context, Submission $submission)
    {
        parent::__construct(func_get_args());
    }
}
