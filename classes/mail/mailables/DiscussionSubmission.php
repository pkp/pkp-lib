<?php

/**
 * @file classes/mail/mailables/DiscussionSubmission.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DiscussionSubmission
 *
 * @ingroup mail_mailables
 *
 * @brief Email sent when a new query is created or a note is added to a query on the submission workflow stage
 */

namespace PKP\mail\mailables;

use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Discussion;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;
use PKP\security\Role;

class DiscussionSubmission extends Mailable
{
    use Recipient;
    use Sender;
    use Configurable;
    use Discussion;

    protected static ?string $name = 'mailable.discussionSubmission.name';
    protected static ?string $description = 'mailable.discussionSubmission.description';
    protected static ?string $emailTemplateKey = 'DISCUSSION_NOTIFICATION_SUBMISSION';
    protected static bool $canDisable = true;
    protected static bool $supportsTemplates = true;
    protected static array $groupIds = [self::GROUP_SUBMISSION];
    protected static array $fromRoleIds = [
        Role::ROLE_ID_SUB_EDITOR,
        Role::ROLE_ID_ASSISTANT,
        Role::ROLE_ID_AUTHOR,
    ];
    protected static array $toRoleIds = [
        Role::ROLE_ID_SUB_EDITOR,
        Role::ROLE_ID_ASSISTANT,
        Role::ROLE_ID_AUTHOR,
    ];

    protected Context $context;

    public function __construct(Context $context, Submission $submission)
    {
        parent::__construct([$context, $submission]);
        $this->context = $context;
    }
}
