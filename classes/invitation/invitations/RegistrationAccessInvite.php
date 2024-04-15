<?php

/**
 * @file invitation/invitations/RegistrationAccessInvite.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RegistrationAccessInvite
 *
 * @ingroup invitations
 *
 * @brief Registration with Access Key invitation
 */

namespace PKP\invitation\invitations;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use Illuminate\Mail\Mailable;
use PKP\config\Config;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\invitation\invitations\enums\InvitationStatus;
use PKP\invitation\models\Invitation;
use PKP\user\User;

class RegistrationAccessInvite extends BaseInvitation
{
    private ?User $user = null;
    private bool $isValidated = false;

    /**
     * Create a new invitation instance.
     */
    public function __construct(
        public ?int $invitedUserId,
        ?int $contextId = null
    ) {
        $expiryDays = Config::getVar('email', 'validation_timeout');

        parent::__construct($invitedUserId, null, $contextId, null, $expiryDays);
    }

    public function getMailable(): ?Mailable
    {
        if (isset($this->mailable)) {
            $url = $this->getAcceptUrl();

            $this->mailable->buildViewDataUsing(function () use ($url) {
                return [
                    'activateUrl' => $url
                ];
            });
        }

        return $this->mailable;
    }

    /**
     */
    public function preDispatchActions(): bool
    {
        Invitation::byStatus(InvitationStatus::PENDING)
            ->byClassName($this->className)
            ->byContextId($this->contextId)
            ->byUserId($this->invitedUserId)
            ->markAs(InvitationStatus::CANCELLED);

        return true;
    }

    /**
     * Fill the Invitation Object with all the neccesary info in order to 
     * continue with the accept handle
     */
    public function finaliseAccept(): void
    {
        $this->user = Repo::user()->get($this->invitedUserId, true);

        if (!$this->user) {
            return;
        }

        $this->isValidated = $this->setUserValid($this->user);

        parent::finaliseAccept();
    }

    public function acceptHandle(): void
    {
        $this->finaliseAccept();

        $request = Application::get()->getRequest();
        $url = PKPApplication::get()->getDispatcher()->url(
            PKPApplication::get()->getRequest(),
            PKPApplication::ROUTE_PAGE,
            null,
            'user',
            'activateUser',
            [
                $this->user->getUsername(),
            ]
        );

        if (isset($this->contextId)) {
            $contextDao = Application::getContextDAO();
            $this->context = $contextDao->getById($this->contextId);

            $url = PKPApplication::get()->getDispatcher()->url(
                PKPApplication::get()->getRequest(),
                PKPApplication::ROUTE_PAGE,
                $this->context->getData('urlPath'),
                'user',
                'activateUser',
                [
                    $this->user->getUsername(),
                ]
            );
        }

        $request->redirectUrl($url);
    }

    /**
     */
    public function declineHandle(): void 
    {
        $this->finaliseDecline();
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
}
