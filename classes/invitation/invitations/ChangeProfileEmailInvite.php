<?php

/**
 * @file invitation/invitations/ChangeProfileEmailInvite.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ChangeProfileEmailInvite
 *
 * @ingroup invitations
 *
 * @brief Change Profile Email invitation
 */

namespace PKP\invitation\invitations;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use Illuminate\Mail\Mailable;
use PKP\config\Config;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\identity\Identity;
use PKP\invitation\invitations\enums\InvitationStatus;
use PKP\invitation\models\Invitation;
use PKP\mail\mailables\ChangeProfileEmailInvitationNotify;
use PKP\session\SessionManager;
use PKP\user\User;

class ChangeProfileEmailInvite extends BaseInvitation
{
    private ?User $user = null;
    private bool $isValidated = false;

    /**
     * Create a new invitation instance.
     */
    public function __construct(
        public int $invitedUserId,
        public string $updatedEmail,
        public int $intoContextId
    ) {
        $expiryDays = Config::getVar('email', 'validation_timeout');

        $contextDao = Application::getContextDAO();
        $this->context = $contextDao->getById($intoContextId);

        $this->user = Repo::user()->get($this->invitedUserId, true);

        parent::__construct($invitedUserId, $updatedEmail, $intoContextId, null, $expiryDays);
    }

    public function getMailable(): ?Mailable
    {
        $sendIdentity = new Identity();
        $sendIdentity->setFamilyName($this->user->getFamilyName(null), null);
        $sendIdentity->setGivenName($this->user->getGivenName(null), null);
        $sendIdentity->setEmail($this->updatedEmail);

        $mailable = new ChangeProfileEmailInvitationNotify($this->context);
        $mailable->recipients([$sendIdentity]);
        $mailable->sender($this->user);

        $mailable->body('{$activateUrl}');
        $mailable->subject('Test');

        $this->setMailable($mailable);
        
        $url = $this->getAcceptUrl();

        $this->mailable->buildViewDataUsing(function () use ($url) {
            return [
                'activateUrl' => $url
            ];
        });

        return $this->mailable;
    }

    /**
     */
    public function preDispatchActions(): bool
    {
        Invitation::byStatus(InvitationStatus::PENDING)
            ->byClassName($this->className)
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

        $this->user->setEmail($this->updatedEmail);
        Repo::user()->edit($this->user);

        $sessionManager = SessionManager::getManager();
        $session = $sessionManager->getUserSession();

        if ($session->getSessionVar('email')) {
            $session->setSessionVar('email', $this->user->getEmail());
        }

        $sessionManager->invalidateSessions($this->user->getId(), $sessionManager->getUserSession()->getId());

        parent::finaliseAccept();
    }

    public function acceptHandle(): void
    {
        $this->finaliseAccept();

        $request = Application::get()->getRequest();
        $url = PKPApplication::get()->getDispatcher()->url(
            PKPApplication::get()->getRequest(),
            PKPApplication::ROUTE_PAGE,
            $this->context->getData('urlPath'),
            'user',
            'profile'
        );

        $request->redirectUrl($url);
    }

    /**
     */
    public function declineHandle(): void 
    {
        $this->finaliseDecline();

        $request = Application::get()->getRequest();
        $url = PKPApplication::get()->getDispatcher()->url(
            PKPApplication::get()->getRequest(),
            PKPApplication::ROUTE_PAGE,
            $this->context->getData('urlPath'),
            'user',
            'profile'
        );

        $request->redirectUrl($url);
    }
}
