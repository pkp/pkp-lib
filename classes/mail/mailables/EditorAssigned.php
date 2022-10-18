<?php

/**
 * @file classes/mail/mailables/EditorAssigned.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorAssigned
 * @ingroup mail_mailables
 *
 * @brief Email sent to editors assigned to a submission.
 */

namespace PKP\mail\mailables;

use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use PKP\security\Role;

class EditorAssigned extends Mailable
{
    use Recipient;
    use Configurable;

    protected static ?string $name = 'mailable.editorAssigned.name';
    protected static ?string $description = 'mailable.editorAssigned.description';
    protected static ?string $emailTemplateKey = 'EDITOR_ASSIGN';
    protected static array $groupIds = [self::GROUP_SUBMISSION];
    protected static array $toRoleIds = [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR];

    public function __construct(Context $context, Submission $submission)
    {
        parent::__construct(func_get_args());
    }
}
