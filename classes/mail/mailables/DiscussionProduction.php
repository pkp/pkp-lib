<?php

/**
 * @file classes/mail/mailables/DiscussionProduction.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DiscussionProduction
 * @ingroup mail_mailables
 *
 * @brief Email sent when a new query is created or a note is added to a query on the production workflow stage
 */

namespace PKP\mail\mailables;

use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\traits\Configurable;
use PKP\mail\Mailable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;

class DiscussionProduction extends Mailable
{
    use Recipient;
    use Sender;
    use Configurable;

    public const SUBJECT = 'subject';
    public const CONTENT = 'content';

    protected static ?string $name = 'mailable.discussionProduction.name';
    protected static ?string $description = 'mailable.discussionProduction.description';
    protected static ?string $emailTemplateKey = 'DISCUSSION_NOTIFICATION';
    protected static bool $canDisable = true;
    protected static array $groupIds = [self::GROUP_PRODUCTION];

    public function __construct(Context $context, Submission $submission, string $subject, string $content)
    {
        parent::__construct(array_slice(func_get_args(), 0, -2));
        $this->addData([
            self::SUBJECT => $subject,
            self::CONTENT => $content,
        ]);
    }

    public static function getDataDescriptions(): array
    {
        $variables = parent::getDataDescriptions();
        $variables[self::SUBJECT] = __('emailTemplate.variable.discussion.subject');
        $variables[self::CONTENT] = __('emailTemplate.variable.discussion.message');
        return $variables;
    }
}