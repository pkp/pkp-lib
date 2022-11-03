<?php

/**
 * @file classes/mail/mailables/SubmissionAcknowledgement.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionAcknowledgement
 * @ingroup mail_mailables
 *
 * @brief This email is sent automatically to an author who made a submission to the journal
 */

namespace PKP\mail\mailables;

use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\traits\Configurable;
use PKP\mail\Mailable;
use PKP\mail\traits\Recipient;
use PKP\security\Role;

class SubmissionAcknowledgement extends Mailable
{
    use Recipient;
    use Configurable;

    protected static ?string $name = 'mailable.submissionAck.name';
    protected static ?string $description = 'mailable.submissionAck.description';
    protected static ?string $emailTemplateKey = 'SUBMISSION_ACK';
    protected static bool $canDisable = true;
    protected static array $groupIds = [self::GROUP_SUBMISSION];
    protected static array $fromRoleIds = [self::FROM_SYSTEM];
    protected static array $toRoleIds = [Role::ROLE_ID_AUTHOR];

    public function __construct(Context $context, Submission $submission)
    {
        parent::__construct(func_get_args());
    }
}
