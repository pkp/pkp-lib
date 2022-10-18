<?php

/**
 * @file classes/mail/mailables/SubmissionSavedForLater.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionSavedForLater
 *
 * @brief Email sent to a submitting author when they save their submission
 *   to be completed later
 */

namespace PKP\mail\mailables;

use APP\decision\Decision;
use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Recipient;
use PKP\security\Role;

class SubmissionSavedForLater extends Mailable
{
    use Recipient;

    /** @var string An email variable that contains a description of the editorial decision */
    public const DECISION_DESCRIPTION = 'decisionDescription';

    protected static ?string $name = 'mailable.submissionSavedForLater.name';
    protected static ?string $description = 'mailable.submissionSavedForLater.description';
    protected static ?string $emailTemplateKey = 'SUBMISSION_SAVED_FOR_LATER';
    protected static bool $supportsTemplates = true;
    protected static array $groupIds = [self::GROUP_SUBMISSION];
    protected static bool $canDisable = true;
    protected static array $toRoleIds = [Role::ROLE_ID_AUTHOR];
    protected Decision $decision;

    public function __construct(Context $context, Submission $submission)
    {
        parent::__construct(func_get_args());
    }
}
