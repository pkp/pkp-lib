<?php

namespace PKP\mail\mailables;

use PKP\mail\traits\OrcidVariables;
use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use PKP\security\Role;

class OrcidRequestAuthorAuthorization extends Mailable
{
    use Configurable;
    use Recipient;
    use OrcidVariables;

    protected static ?string $name = 'orcidProfile.orcidRequestAuthorAuthorization.name';
    protected static ?string $description = 'emails.orcidRequestAuthorAuthorization.description';
    protected static ?string $emailTemplateKey = 'ORCID_REQUEST_AUTHOR_AUTHORIZATION';
    protected static array $toRoleIds = [Role::ROLE_ID_AUTHOR];

    public function __construct(Context $context, Submission $submission, string $oauthUrl)
    {
        parent::__construct([$context, $submission]);
        $this->setupOrcidVariables($oauthUrl);
    }

    public static function getDataDescriptions(): array
    {
        return array_merge(
            parent::getDataDescriptions(),
            static::getOrcidDataDescriptions()
        );
    }
}
