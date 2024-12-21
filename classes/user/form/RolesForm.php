<?php

/**
 * @file classes/user/form/RolesForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RolesForm
 *
 * @ingroup user_form
 *
 * @brief Form to edit the roles area of the user profile.
 */

namespace PKP\user\form;

use APP\core\Application;
use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\user\User;
use PKP\userGroup\UserGroup;

class RolesForm extends BaseProfileForm
{
    /**
     * Constructor.
     *
     * @param User $user
     */
    public function __construct($user)
    {
        parent::__construct('user/rolesForm.tpl', $user);
    }

    /**
     * @copydoc BaseProfileForm::fetch
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);

        $userGroupIds = UserGroup::query()
            ->withUserIds([$request->getUser()->getId()])
            ->get()
            ->pluck('id')
            ->toArray();

        $templateMgr->assign('userGroupIds', $userGroupIds);

        $userFormHelper = new UserFormHelper();
        $userFormHelper->assignRoleContent($templateMgr, $request);

        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc BaseProfileForm::initData()
     */
    public function initData()
    {
        $user = $this->getUser();

        $this->_data = [
            'interests' => Repo::userInterest()->getInterestsForUser($user),
        ];
    }

    /**
     * Assign form data to user-submitted data.
     */
    public function readInputData()
    {
        parent::readInputData();

        $this->readUserVars([
            'authorGroup',
            'reviewerGroup',
            'readerGroup',
            'interests',
        ]);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $request = Application::get()->getRequest();
        $user = $request->getUser();

        // Save the roles
        $userFormHelper = new UserFormHelper();
        $userFormHelper->saveRoleContent($this, $user);

        // Insert the user interests
        Repo::userInterest()->setInterestsForUser($user, $this->getData('interests'));

        parent::execute(...$functionArgs);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\user\form\RolesForm', '\RolesForm');
}
