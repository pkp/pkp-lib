<?php

/**
 * @file invitation/invitations/RegistrationAccessInvite.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
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
use APP\template\TemplateManager;
use Illuminate\Mail\Mailable;
use PKP\config\Config;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\invitation\invitations\BaseInvitation;
use PKP\mail\variables\ReviewAssignmentEmailVariable;
use PKP\security\Validation;
use PKP\session\SessionManager;
use PKP\user\User;
use ReviewAssignment;

class RegistrationAccessInvite extends BaseInvitation
{
    /**
     * Create a new invitation instance.
     */
    public function __construct(
        public ?int $invitedUserId, 
        ?string $email, 
        int $contextId
    )
    {
        $expiryDays = Config::getVar('email', 'validation_timeout');

        parent::__construct($invitedUserId, $email, $contextId, null, $expiryDays);
    }

    public function getInvitationMailable(): ?Mailable 
    {
        if (isset($this->mailable)) {
            $url = $this->getAcceptInvitationUrl();

            $this->mailable->buildViewDataUsing(function () use ($url) {
                return [
                    'activateUrl' => $url
                ];
            });
        }
        
        return $this->mailable;
    }
    
    /**
     * @return bool
     */
    public function preDispatchActions(): bool 
    {
        $hadCancels = Repo::invitation()
            ->cancelInvitationFamily($this->className, $this->email, $this->contextId, null);

        return true;
    }

    public function invitationAcceptHandle() : void
    {
        $user = Repo::user()->get($this->invitedUserId, true);

        $request = Application::get()->getRequest();
        $validated = $this->_validateAccessKey($user, $request);

        if ($validated) {
            parent::invitationAcceptHandle();
        }

        $url = PKPApplication::get()->getDispatcher()->url(
            PKPApplication::get()->getRequest(),
            PKPApplication::ROUTE_PAGE,
            null, 
            'user', 
            'activateUser',
            null,
            [
                'username' => $user->getUsername(),
                'validated' => $validated
            ]
        );

        if ($this->contextId != PKPApplication::CONTEXT_SITE) {
            $contextDao = Application::getContextDAO();
            $this->context = $contextDao->getById($this->contextId);

            $url = PKPApplication::get()->getDispatcher()->url(
                PKPApplication::get()->getRequest(),
                PKPApplication::ROUTE_PAGE,
                $this->context->getData('urlPath'),
                'user', 
                'activateUser',
                null,
                [
                    'username' => $user->getUsername(),
                    'validated' => $validated
                ]
            );
        }
        
        $request->redirectUrl($url);
    }

    private function _validateAccessKey(User $user, Request $request) : bool
    {
        if (!$user) {
            return false;
        }

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
