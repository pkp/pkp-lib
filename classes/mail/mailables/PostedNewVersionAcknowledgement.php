<?php

/**
 * @file classes/mail/mailables/PostedNewVersionAcknowledgement.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PostedNewVersionAcknowledgement
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

class PostedNewVersionAcknowledgement extends Mailable
{
    use Recipient;
    use Configurable;

    protected static ?string $name = 'mailable.postedNewVersionAck.name';
    protected static ?string $description = 'mailable.postedNewVersionAck.description';
    protected static ?string $emailTemplateKey = 'POSTED_NEW_VERSION_ACK';
    protected static bool $canDisable = true;
    protected static array $groupIds = [self::GROUP_SUBMISSION];
    protected static array $toRoleIds = [Role::ROLE_ID_AUTHOR];

    public function __construct(Context $context, Submission $submission)
    {
        parent::__construct(func_get_args());
    }
}
