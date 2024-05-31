<?php

/**
 * @file classes/invitation/invitations/RegistrationAccessInvite.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RegistrationAccessInvite
 *
 * @brief Registration with Access Key invitation
 */

namespace PKP\invitation\invitations;

use APP\facades\Repo;
use Exception;
use Illuminate\Mail\Mailable;
use PKP\core\Core;
use PKP\invitation\core\enums\InvitationAction;
use PKP\invitation\core\enums\InvitationStatus;
use PKP\invitation\core\contracts\IBackofficeHandleable;
use PKP\invitation\core\contracts\IMailableUrlUpdateable;
use PKP\invitation\core\Invitation;
use PKP\invitation\core\PKPInvitationActionRedirectController;
use PKP\invitation\models\InvitationModel;
use PKP\invitation\invitations\handlers\RegistrationAccessInviteRedirectController;
use PKP\user\User;

class RegistrationAccessInvite extends Invitation implements IBackofficeHandleable, IMailableUrlUpdateable
{
    const INVITATION_TYPE = 'registrationAccess';

    public static function getType(): string 
    {
        return self::INVITATION_TYPE;
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

    protected function preDispatchActions(): void
    {
        $pendingInvitations = InvitationModel::byStatus(InvitationStatus::PENDING)
            ->byType(self::INVITATION_TYPE)
            ->byContextId($this->invitationModel->contextId)
            ->byUserId($this->invitationModel->userId)
            ->get();

        foreach($pendingInvitations as $pendingInvitation) {
            $pendingInvitation->markAs(InvitationStatus::CANCELLED);
        }
    }

    public function finalise(): void
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

    public function getInvitationActionRedirectController(): ?PKPInvitationActionRedirectController
    {
        return new RegistrationAccessInviteRedirectController($this);
    }
}
