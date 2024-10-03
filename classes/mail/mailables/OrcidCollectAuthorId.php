<?php

/**
 * @file classes/mail/mailables/OrcidCollectAuthorId.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OrcidCollectAuthorId
 *
 * @brief An automatic email sent to the authors to add ORCIDs to a submission.
 */

namespace PKP\mail\mailables;

use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\OrcidVariables;
use PKP\mail\traits\Recipient;
use PKP\security\Role;

class OrcidCollectAuthorId extends Mailable
{
    use Configurable;
    use Recipient;
    use OrcidVariables;

    protected static ?string $name = 'orcid.orcidCollectAuthorId.name';
    protected static ?string $description = 'emails.orcidCollectAuthorId.description';
    protected static ?string $emailTemplateKey = 'ORCID_COLLECT_AUTHOR_ID';
    protected static array $toRoleIds = [Role::ROLE_ID_AUTHOR];

    public function __construct(Context $context, Submission $submission, string $oauthUrl)
    {
        parent::__construct([$context, $submission]);
        $this->setupOrcidVariables($oauthUrl, $context);
    }

    /**
     * Adds ORCID URLs to email template
     */
    public static function getDataDescriptions(): array
    {
        return array_merge(
            parent::getDataDescriptions(),
            static::getOrcidDataDescriptions()
        );
    }
}
