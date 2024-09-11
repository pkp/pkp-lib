<?php

/**
 * @file classes/invitation/invitations/registrationAccess/RegistrationAccessInvite.php
 *
 * Copyright (c) 2023-2024 Simon Fraser University
 * Copyright (c) 2023-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RegistrationAccessInvite
 *
 * @brief Registration with Access Key invitation
 */

namespace PKP\invitation\invitations\registrationAccess;

use APP\facades\Repo;
use Exception;
use Illuminate\Mail\Mailable;
use PKP\core\Core;
use PKP\invitation\core\contracts\IBackofficeHandleable;
use PKP\invitation\core\contracts\IMailableUrlUpdateable;
use PKP\invitation\core\EmptyInvitePayload;
use PKP\invitation\core\enums\InvitationAction;
use PKP\invitation\core\enums\InvitationStatus;
use PKP\invitation\core\Invitation;
use PKP\invitation\core\InvitationActionRedirectController;
use PKP\invitation\invitations\registrationAccess\handlers\RegistrationAccessInviteRedirectController;
use PKP\user\User;

class RegistrationAccessInvite extends Invitation implements IBackofficeHandleable, IMailableUrlUpdateable
{
    public const INVITATION_TYPE = 'registrationAccess';

    /**
     * @inheritDoc
     */
    public static function getType(): string
    {
        return self::INVITATION_TYPE;
    }

    /**
     * @inheritDoc
     */
    protected function getPayloadClass(): string
    {
        return EmptyInvitePayload::class;
    }

    /**
     * @inheritDoc
     */
    public function getPayload(): EmptyInvitePayload
    {
        return parent::getPayload();
    }

    public function updateMailableWithUrl(Mailable $mailable): void
    {
        $url = $this->getActionURL(InvitationAction::ACCEPT);

        $mailable->buildViewDataUsing(function () use ($url) {
            return [
                'activateUrl' => $url
            ];
        });
    }

    public function finalize(): void
    {
        $user = Repo::user()->get($this->invitationModel->userId, true);

        if (!$user) {
            throw new Exception();
        }

        if (!$this->setUserValid($user)) {
            throw new Exception();
        }

        $this->invitationModel->markAs(InvitationStatus::ACCEPTED);
    }

    private function setUserValid(User $user): bool
    {
        if ($user->getDateValidated() === null) {
            // Activate user
            $user->setDisabled(false);
            $user->setDisabledReason('');
            $user->setDateValidated(Core::getCurrentDate());
            Repo::user()->edit($user);

            return true;
        }

        return false;
    }

    public function getInvitationActionRedirectController(): ?InvitationActionRedirectController
    {
        return new RegistrationAccessInviteRedirectController($this);
    }
}
