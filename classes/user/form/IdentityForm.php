<?php

/**
 * @file classes/user/form/IdentityForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class IdentityForm
 * @ingroup user_form
 *
 * @brief Form to edit user's identity information.
 */

namespace PKP\user\form;

use APP\core\Application;

use APP\template\TemplateManager;

class IdentityForm extends BaseProfileForm
{
    /**
     * Constructor.
     *
     * @param User $user
     */
    public function __construct($user)
    {
        parent::__construct('user/identityForm.tpl', $user);

        // the users register for the site, thus
        // the site primary locale is the required default locale
        $site = Application::get()->getRequest()->getSite();
        $this->addSupportedFormLocale($site->getPrimaryLocale());

        // Validation checks for this form
        $form = $this;
        $this->addCheck(new \PKP\form\validation\FormValidatorLocale($this, 'givenName', 'required', 'user.profile.form.givenNameRequired', $site->getPrimaryLocale()));
        $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'familyName', 'optional', 'user.profile.form.givenNameRequired.locale', function ($familyName) use ($form) {
            $givenNames = $form->getData('givenName');
            foreach ($familyName as $locale => $value) {
                if (!empty($value) && empty($givenNames[$locale])) {
                    return false;
                }
            }
            return true;
        }));
    }

    /**
     * @copydoc BaseProfileForm::fetch
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);

        $user = $this->getUser();
        $templateMgr->assign([
            'username' => $user->getUsername(),
        ]);

        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc BaseProfileForm::initData()
     */
    public function initData()
    {
        $user = $this->getUser();

        $this->_data = [
            'givenName' => $user->getGivenName(null),
            'familyName' => $user->getFamilyName(null),
            'preferredPublicName' => $user->getPreferredPublicName(null),
        ];
    }

    /**
     * Assign form data to user-submitted data.
     */
    public function readInputData()
    {
        parent::readInputData();

        $this->readUserVars([
            'givenName', 'familyName', 'preferredPublicName',
        ]);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $request = Application::get()->getRequest();
        $user = $request->getUser();

        $user->setGivenName($this->getData('givenName'), null);
        $user->setFamilyName($this->getData('familyName'), null);
        $user->setPreferredPublicName($this->getData('preferredPublicName'), null);

        parent::execute(...$functionArgs);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\user\form\IdentityForm', '\IdentityForm');
}
