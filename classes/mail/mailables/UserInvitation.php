<?php

namespace PKP\mail\mailables;

use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;
use PKP\security\Role;
use PKP\user\User;

class UserInvitation extends Mailable
{
    use Recipient {
        recipients as traitRecipients;
    }
    use Configurable;
    use Sender;

    protected static ?string $name = 'mailable.userInvitation.name';
    protected static ?string $description = 'mailable.userInvitation.description';
    protected static ?string $emailTemplateKey = 'USER_INVITATION';
    protected static array $groupIds = [self::GROUP_OTHER];
    protected static array $fromRoleIds = [
        Role::ROLE_ID_SUB_EDITOR,
    ];
    protected static array $toRoleIds = [
        Role::ROLE_ID_SUB_EDITOR,
        Role::ROLE_ID_ASSISTANT,
        Role::ROLE_ID_AUTHOR,
        Role::ROLE_ID_READER,
        Role::ROLE_ID_REVIEWER,
        Role::ROLE_ID_SUBSCRIPTION_MANAGER,
    ];
    protected static ?string $variableAcceptUrl = 'acceptUrl';
    protected static ?string $variableDeclineUrl = 'declineUrl';

    protected string $acceptUrl;
    protected string $declineUrl;
    public function __construct(Context $context, string $acceptUrl, string $declineUrl)
    {
        parent::__construct([$context]);
        $this->acceptUrl = $acceptUrl;
        $this->declineUrl = $declineUrl;
    }
    /**
     * @copydoc Mailable::getDataDescriptions()
     */
    public static function getDataDescriptions(): array
    {
        $variables = parent::getDataDescriptions();
        return static::addPasswordVariable($variables);
    }

    /**
     * Add a description to a new password variable
     */
    protected static function addPasswordVariable(array $variables): array
    {
        $variables[static::$variableAcceptUrl] = __('emailTemplate.variable.acceptUrl');
        $variables[static::$variableDeclineUrl] = __('emailTemplate.variable.declineUrl');
        return $variables;
    }

    /**
     * Override trait's method to include user invitation url variable
     */
    public function recipients(User $recipient, ?string $locale = null): Mailable
    {
        $this->traitRecipients([$recipient], $locale);
        $this->addData([
            static::$variableAcceptUrl => htmlspecialchars($this->acceptUrl),
            static::$variableDeclineUrl => htmlspecialchars($this->declineUrl)
        ]);

        return $this;
    }
}
