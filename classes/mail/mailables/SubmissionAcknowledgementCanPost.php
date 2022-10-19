<?php

/**
 * @file classes/mail/mailables/SubmissionAcknowledgementCanPost.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionAcknowledgementCanPost
 * @ingroup mail_mailables
 *
 * @brief Email sent to a submitting author when they submit their submission
 *   and they can post the preprint right away.
 */

namespace APP\mail\mailables;

use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use PKP\security\Role;

class SubmissionAcknowledgementCanPost extends Mailable
{
    use Recipient;
    use Configurable;

    protected static ?string $name = 'mailable.submissionAckCanPost.name';
    protected static ?string $description = 'mailable.submissionAckCanPost.description';
    protected static ?string $emailTemplateKey = 'SUBMISSION_ACK_CAN_POST';
    protected static bool $canDisable = true;
    protected static array $groupIds = [self::GROUP_SUBMISSION];
    protected static array $toRoleIds = [Role::ROLE_ID_AUTHOR];

    public function __construct(Context $context, Submission $submission)
    {
        parent::__construct(func_get_args());
    }
}
