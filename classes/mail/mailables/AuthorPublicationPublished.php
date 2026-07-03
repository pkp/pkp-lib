<?php

/**
 * @file classes/mail/mailables/AuthorPublicationPublished.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorPublicationPublished
 *
 * @brief Email is sent automatically to authors when a publication is published.
 */

namespace PKP\mail\mailables;

use APP\publication\Publication;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use PKP\security\Role;

class AuthorPublicationPublished extends Mailable
{
    use Configurable;
    use Recipient;

    protected static ?string $name = 'mailable.authorPublicationPublished.name';
    protected static ?string $description = 'mailable.authorPublicationPublished.description';
    protected static ?string $emailTemplateKey = 'AUTHOR_PUBLICATION_PUBLISHED';
    protected static array $groupIds = [self::GROUP_SUBMISSION];
    protected static array $fromRoleIds = [self::FROM_SYSTEM];
    protected static array $toRoleIds = [Role::ROLE_ID_AUTHOR];

    public function __construct(protected Context $context, protected Publication $publication)
    {
        parent::__construct(func_get_args());
    }
}
