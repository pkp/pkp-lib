<?php

/**
 * @file controllers/grid/settings/user/form/UserDisableForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserDisableForm
 * @ingroup controllers_grid_settings_user_form
 *
 * @brief Form for enabling/disabling a user
 */

use APP\template\TemplateManager;

use PKP\form\Form;

class UserDisableForm extends Form
{
    /** @var the user id of user to enable/disable */
    public $_userId;

    /** @var whether to enable or disable the user */
    public $_enable;

    /**
     * Constructor.
     */
    public function __construct($userId, $enable = false)
    {
        parent::__construct('controllers/grid/settings/user/form/userDisableForm.tpl');

        $this->_userId = (int) $userId;
        $this->_enable = (bool) $enable;

        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }

    /**
     * Initialize form data.
     */
    public function initData()
    {
        if ($this->_userId) {
            $userDao = DAORegistry::getDAO('UserDAO'); /** @var UserDAO $userDao */
            $user = $userDao->getById($this->_userId);

            if ($user) {
                $this->_data = [
                    'disableReason' => $user->getDisabledReason()
                ];
            }
        }
    }

    /**
     * Assign form data to user-submitted data.
     *
     * @see Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(
            [
                'disableReason',
            ]
        );
    }

    /**
     * @copydoc Form::display
     *
     * @param null|mixed $request
     * @param null|mixed $template
     */
    public function display($request = null, $template = null)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'userId' => $this->_userId,
            'enable' => $this->_enable,
        ]);
        return $this->fetch($request);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $userDao = DAORegistry::getDAO('UserDAO'); /** @var UserDAO $userDao */
        $user = $userDao->getById($this->_userId);

        if ($user) {
            $user->setDisabled($this->_enable ? false : true);
            $user->setDisabledReason($this->getData('disableReason'));
            $userDao->updateObject($user);
        }
        parent::execute(...$functionArgs);
        return $user;
    }
}
