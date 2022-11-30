<?php

/**
 * @file classes/mail/mailables/SubmissionAcknowledgementNotAuthor.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionAcknowledgementNotAuthor
 * @ingroup mail_mailables
 *
 * @brief This email is sent automatically to the contributors identified in a submission to the journal
 */

namespace PKP\mail\mailables;

use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\traits\Configurable;
use PKP\mail\Mailable;
use PKP\mail\traits\Recipient;
use PKP\security\Role;
use PKP\user\User;

class SubmissionAcknowledgementNotAuthor extends Mailable
{
    use Recipient;
    use Configurable;

    protected static ?string $name = 'mailable.submissionAckNotAuthor.name';
    protected static ?string $description = 'mailable.submissionAckNotAuthor.description';
    protected static ?string $emailTemplateKey = 'SUBMISSION_ACK_NOT_USER';
    protected static bool $canDisable = true;
    protected static array $groupIds = [self::GROUP_SUBMISSION];
    protected static array $fromRoleIds = [self::FROM_SYSTEM];
    protected static array $toRoleIds = [Role::ROLE_ID_AUTHOR];

    protected static string $submitterName = 'submitterName';

    public function __construct(Context $context, Submission $submission, User $submitter)
    {
        parent::__construct(array_slice(func_get_args(), 0, -1));

        $this->addData([
            self::$submitterName => $submitter->getFullName()
        ]);
    }

    public static function getDataDescriptions(): array
    {
        $variables = parent::getDataDescriptions();
        return static::addSubmitterNameDescription($variables);
    }

    protected static function addSubmitterNameDescription(array $variables): array
    {
        $variables[self::$submitterName] = __('emailTemplate.variable.submitterName');
        return $variables;
    }
}
