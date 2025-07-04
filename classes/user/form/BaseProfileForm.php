<?php

/**
 * @file classes/user/form/BaseProfileForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BaseProfileForm
 *
 * @ingroup user_form
 *
 * @brief Base form to edit an aspect of user profile.
 */

namespace PKP\user\form;

use APP\core\Application;
use APP\facades\Repo;
use PKP\form\Form;
use PKP\invitation\invitations\changeProfileEmail\ChangeProfileEmailInvite;
use PKP\user\User;

abstract class BaseProfileForm extends Form
{
    /** @var User */
    public $_user;

    /**
     * Constructor.
     *
     * @param string $template
     * @param User $user
     */
    public function __construct($template, $user)
    {
        parent::__construct($template);

        $this->_user = $user;
        assert(isset($user));

        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }

    /**
     * Get the user associated with this profile
     */
    public function getUser()
    {
        return $this->_user;
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        parent::execute(...$functionArgs);

        $request = Application::get()->getRequest();
        $user = $request->getUser(); // TODO:: ?? Why not $this->getUser()
        Repo::user()->edit($user);

        if ($functionArgs['emailUpdated'] ?? false) {
            $sessionGuard = Application::get()->getRequest()->getSessionGuard();
            $sessionGuard->setUserDataToSession($user)->updateSession($user->getId());
            $sessionGuard->invalidateOtherSessions($user->getId(), $request->getSession()->getId());

            $invite = new ChangeProfileEmailInvite();

            $invite->initialize($user->getId());

            $invite->getPayload()->newEmail = $functionArgs['emailUpdated'];

            $inviteResult = false;
            $updateResult = $invite->updatePayload();
            if ($updateResult) {
                $inviteResult = $invite->invite();
            }

            if (!$inviteResult) {
                throw new \Exception('Invitation could be send');
            }
        }
    }
}
