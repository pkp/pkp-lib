<?php

/**
 * @file classes/mail/mailables/ValidateEmailSite.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ValidateEmailSite
 * @ingroup mail_mailables
 *
 * @brief Represents registration validation email
 */

namespace PKP\mail\mailables;

use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use PKP\site\Site;

class ValidateEmailSite extends Mailable
{
    use Configurable;
    use Recipient;

    protected static ?string $name = 'mailable.validateEmailSite.name';
    protected static ?string $description = 'mailable.validateEmailSite.description';
    protected static ?string $emailTemplateKey = 'USER_VALIDATE_SITE';

    public function __construct(Site $site)
    {
        parent::__construct(func_get_args());
    }
}
