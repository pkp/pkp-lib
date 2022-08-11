<?php

/**
 * @file classes/mail/mailables/RevisedVersionNotify.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RevisedVersionNotify
 * @ingroup mail_mailables
 *
 * @brief The email is sent automatically to the assigned editor when author uploads a revised version of an article
 */

namespace PKP\mail\mailables;

use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\traits\Configurable;
use PKP\mail\Mailable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;
use PKP\security\Role;
use PKP\submission\reviewRound\ReviewRound;
use PKP\user\User;

class RevisedVersionNotify extends Mailable
{
    use Recipient;
    use Sender;
    use Configurable;

    protected static ?string $name = 'mailable.revisedVersionNotify.name';
    protected static ?string $description = 'mailable.revisedVersionNotify.description';
    protected static ?string $emailTemplateKey = 'REVISED_VERSION_NOTIFY';
    protected static bool $supportsTemplates = false;
    protected static array $groupIds = [self::GROUP_REVIEW];
    protected static array $toRoleIds = [Role::ROLE_ID_MANAGER];

    protected Submission $submission;
    protected static string $submitterName = 'submitterName';

    public function __construct(Context $context, Submission $submission, User $uploader, ReviewRound $reviewRound)
    {
        parent::__construct(array_slice(func_get_args(), 0, -2));
        $this->setupSubmitterNameVariable($uploader);
    }

    /**
     * Add description to a submissionUrl email template variable
     */
    public static function getDataDescriptions(): array
    {
        $variables = parent::getDataDescriptions();
        $variables[static::$submitterName] = __('emailTemplate.variable.submitterName');
        return $variables;
    }

    /**
     * Add submitterName variable; submitter may not be a sender
     */
    protected function setupSubmitterNameVariable(User $uploader): void
    {
        $this->addData([static::$submitterName => $uploader->getFullName()]);
    }
}
