<?php

/**
 * @file classes/mail/mailables/ValidateEmailContext.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ValidateEmailContext
 * @ingroup mail_mailables
 *
 * @brief Represents registration validation email
 */

namespace PKP\mail\mailables;

use PKP\context\Context;
use PKP\facades\Repo;
use PKP\mail\Mailable;
use PKP\emailTemplate\EmailTemplate;
use PKP\mail\Recipient;

class ValidateEmailContext extends Mailable
{
    use Recipient;

    public const EMAIL_KEY = 'USER_VALIDATE_CONTEXT';

    protected static ?string $name = 'mailable.validateEmailContext.name';

    protected static ?string $description = 'mailable.validateEmailContext.description';

    public function __construct(Context $context)
    {
        parent::__construct(func_get_args());
    }

    public function getTemplate(int $contextId) : EmailTemplate
    {
        return Repo::emailTemplate()->getByKey($contextId, self::EMAIL_KEY);
    }
}
