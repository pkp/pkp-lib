<?php

/**
 * @file classes/mail/mailables/SubmissionNeedsEditor.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionNeedsEditor
 * @ingroup mail_mailables
 *
 * @brief Email sent to managers when a new submission is made and no editor is assigned
 */

namespace PKP\mail\mailables;

use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use PKP\security\Role;

class SubmissionNeedsEditor extends Mailable
{
    use Recipient;
    use Configurable;

    protected static ?string $name = 'mailable.submissionNeedsEditor.name';
    protected static ?string $description = 'mailable.submissionNeedsEditor.description';
    protected static ?string $emailTemplateKey = 'SUBMISSION_NEEDS_EDITOR';
    protected static bool $canDisable = true;
    protected static array $groupIds = [self::GROUP_SUBMISSION];
    protected static array $toRoleIds = [Role::ROLE_ID_AUTHOR];

    public function __construct(Context $context, Submission $submission)
    {
        parent::__construct(func_get_args());
    }
}
