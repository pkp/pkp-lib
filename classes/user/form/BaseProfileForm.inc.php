<?php

/**
 * @file classes/user/form/BaseProfileForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BaseProfileForm
 * @ingroup user_form
 *
 * @brief Base form to edit an aspect of user profile.
 */

use PKP\form\Form;

abstract class BaseProfileForm extends Form
{
    /** @var User */
    public $_user;

    /**
     * Constructor.
     *
     * @param $template string
     * @param $user User
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
        $user = $request->getUser();
        $userDao = DAORegistry::getDAO('UserDAO'); /** @var UserDAO $userDao */
        $userDao->updateObject($user);

        if ($user->getAuthId()) {
            $authDao = DAORegistry::getDAO('AuthSourceDAO'); /** @var AuthSourceDAO $authDao */
            $auth = $authDao->getPlugin($user->getAuthId());
        }

        if (isset($auth)) {
            $auth->doSetUserInfo($user);
        }
    }
}
