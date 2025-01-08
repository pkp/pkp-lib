<?php

/**
 * @file controllers/grid/settings/user/form/UserForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserForm
 *
 * @ingroup controllers_grid_settings_user_form
 *
 * @brief Base class for user forms.
 */

namespace PKP\controllers\grid\settings\user\form;

use APP\core\Request;
use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\userGroup\UserGroup;
use PKP\userGroup\relationships\UserUserGroup;

class UserForm extends Form
{
    /** @var int Id of the user being edited */
    public $userId;

    /**
     * Constructor.
     *
     * @param int $userId optional
     */
    public function __construct($template, $userId = null)
    {
        parent::__construct($template);

        $this->userId = isset($userId) ? (int) $userId : null;

        if (!is_null($userId)) {
            $this->addCheck(new \PKP\form\validation\FormValidator($this, 'userGroupIds', 'required', 'manager.users.roleRequired'));
        }
    }

    /**
     * Initialize form data from current user profile.
     */
    public function initData()
    {
        $userGroupIds = $masthead = [];

        if (!is_null($this->userId)) {
            // fetch user groups where the user is assigned
            $userGroups = UserGroup::query()
                ->whereHas('userUserGroups', function ($query) {
                    $query->withUserId($this->userId)
                          ->withActive();
                })
                ->get();
    
            foreach ($userGroups as $userGroup) {
                $userGroupIds[] = $userGroup->id;
                $masthead[$userGroup->id] = Repo::userGroup()->userOnMasthead($this->userId, $userGroup->id);
            }
        }

        $this->setData('userGroupIds', $userGroupIds);
        $this->setData('masthead', $masthead);

        parent::initData();
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['userGroupIds']);
        parent::readInputData();
    }

    /**
     * @copydoc Form::display
     *
     * @param null|mixed $request
     * @param null|mixed $template
     */
    public function display($request = null, $template = null)
    {
        $contextId = $request->getContext()?->getId() ?? \PKP\core\PKPApplication::SITE_CONTEXT_ID;
        $templateMgr = TemplateManager::getManager($request);

        $allUserGroups = [];

        $userGroups = UserGroup::withContextIds([$contextId])->get();

        foreach ($userGroups as $userGroup) {
            $allUserGroups[(int) $userGroup->id] = $userGroup->getLocalizedData('name');
        }

        $templateMgr->assign([
            'allUserGroups' => $allUserGroups,
            'assignedUserGroups' => array_map(intval(...), $this->getData('userGroupIds')),
        ]);

        return $this->fetch($request);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        parent::execute(...$functionArgs);
    }

    /**
     * Save the user group assignments
     */
    public function saveUserGroupAssignments(Request $request): void
    {
        if (!isset($this->userId)) {
            return;
        }

        if ($this->getData('userGroupIds')) {
            $contextId = $request->getContext()->getId();
    
            // get current user group IDs
            $oldUserGroupIds = UserGroup::query()
                ->whereHas('userUserGroups', function ($query) {
                    $query->withUserId($this->userId)
                          ->withActive();
                })
                ->pluck('user_group_id')
                ->all();
    
            $userGroupsToEnd = array_diff($oldUserGroupIds, $this->getData('userGroupIds'));
            collect($userGroupsToEnd)
                ->each(
                    fn ($userGroupId) =>
                    UserUserGroup::query()
                        ->withUserId($this->userId)
                        ->withUserGroupIds([$userGroupId])
                        ->withActive()
                        ->update(['date_end' => now()])
                );

            $userGroupsToAdd = array_diff($this->getData('userGroupIds'), $oldUserGroupIds);
            collect($userGroupsToAdd)
                ->each(
                    fn ($userGroupId) =>
                    UserUserGroup::create([
                        'userId' => $this->userId,
                        'userGroupId' => $userGroupId,
                        'dateStart' => now(),
                    ])
                );
        }
    }
}
