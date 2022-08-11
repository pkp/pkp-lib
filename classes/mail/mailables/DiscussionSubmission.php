<?php

/**
 * @file classes/mail/mailables/DiscussionSubmission.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DiscussionSubmission
 * @ingroup mail_mailables
 *
 * @brief Email sent when a new query is created or a note is added to a query on the submission workflow stage
 */

namespace PKP\mail\mailables;

use PKP\context\Context;
use PKP\mail\traits\Configurable;
use PKP\mail\Mailable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;
use APP\submission\Submission;

class DiscussionSubmission extends Mailable
{
    use Recipient;
    use Sender;
    use Configurable;

    protected static ?string $name = 'mailable.discussionSubmission.name';
    protected static ?string $description = 'mailable.discussionSubmission.description';
    protected static bool $canDisable = true;
    protected static array $groupIds = [self::GROUP_SUBMISSION];

    public function __construct(Context $context, Submission $submission)
    {
        parent::__construct(func_get_args());
    }
}
