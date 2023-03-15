<?php

/**
 * @file classes/user/form/ChangePasswordForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ChangePasswordForm
 * @ingroup user_form
 *
 * @brief Form to change a user's password.
 */

namespace PKP\user\form;

use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\form\Form;

use PKP\security\Validation;
use PKP\site\Site;
use PKP\user\User;

class ChangePasswordForm extends Form
{
    /** @var User */
    public $_user;

    /** @var Site */
    public $_site;

    /**
     * Constructor.
     * @param User $user
     * @param Site $site
     */
    public function __construct($user, $site)
    {
        parent::__construct('user/changePassword.tpl');

        $this->_user = $user;
        $this->_site = $site;

        // Validation checks for this form
        $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'oldPassword', 'required', 'user.profile.form.oldPasswordInvalid', function ($password) use ($user) {
            return Validation::checkCredentials($user->getUsername(), $password);
        }));
        $this->addCheck(new \PKP\form\validation\FormValidatorLength($this, 'password', 'required', 'user.register.form.passwordLengthRestriction', '>=', $site->getMinPasswordLength()));
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'password', 'required', 'user.profile.form.newPasswordRequired'));
        $form = $this;
        $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'password', 'required', 'user.register.form.passwordsDoNotMatch', function ($password) use ($form) {
            return $password == $form->getData('password2');
        }));
        $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'password', 'required', 'user.profile.form.passwordSameAsOld', function ($password) use ($form) {
            return $password != $form->getData('oldPassword');
        }));

        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }

    /**
     * Get the user associated with this password
     */
    public function getUser()
    {
        return $this->_user;
    }

    /**
     * Get the site
     */
    public function getSite()
    {
        return $this->_site;
    }

    /**
     * @copydoc Form::fetch
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager();
        $templateMgr->assign([
            'minPasswordLength' => $this->getSite()->getMinPasswordLength(),
            'username' => $this->getUser()->getUsername(),
        ]);
        return parent::fetch($request, $template, $display);
    }

    /**
     * Assign form data to user-submitted data.
     */
    public function readInputData()
    {
        $this->readUserVars(['oldPassword', 'password', 'password2']);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $user = $this->getUser();
        $user->setPassword(Validation::encryptCredentials($user->getUsername(), $this->getData('password')));
        parent::execute(...$functionArgs);
        Repo::user()->edit($user);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\user\form\ChangePasswordForm', '\ChangePasswordForm');
}
