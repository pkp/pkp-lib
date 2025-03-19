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
use PKP\security\Role;
use PKP\userGroup\relationships\enums\UserUserGroupMastheadStatus;
use PKP\userGroup\relationships\UserUserGroup;
use PKP\userGroup\UserGroup;

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
        $userGroupIds = $notOnMastheadUserGroupIds = [];

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
            }

            // Get user group IDs for user groups this user should not be displayed on the masthead for
            // The others will be selected per default
            $notOnMastheadUserGroupIds = UserUserGroup::query()
                ->withUserId($this->userId)
                ->withActive()
                ->withMastheadOff()
                ->get()
                ->map(
                    fn (UserUserGroup $userUserGroup) => $userUserGroup->userGroupId
                )
                ->all();

        }

        $this->setData('userGroupIds', $userGroupIds);
        $this->setData('notOnMastheadUserGroupIds', $notOnMastheadUserGroupIds);

        parent::initData();
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['userGroupIds', 'mastheadUserGroupIds']);
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

        $allUserGroups = $defaultMastheadUserGroups = [];

        $userGroups = UserGroup::withContextIds([$contextId])->get();

        foreach ($userGroups as $userGroup) {
            $allUserGroups[(int) $userGroup->id] = $userGroup->getLocalizedData('name');
            if ($userGroup->roleId != Role::ROLE_ID_REVIEWER) {
                $defaultMastheadUserGroups[(int) $userGroup->id] = $userGroup->getLocalizedData('name');
            }
        }

        $templateMgr->assign([
            'allUserGroups' => $allUserGroups,
            'assignedUserGroups' => array_map(intval(...), $this->getData('userGroupIds')),
            'defaultMastheadUserGroups' => $defaultMastheadUserGroups,
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
            $allUserGroupIds = UserGroup::withContextIds([$contextId])
                ->get()
                ->map(
                    fn (UserGroup $userGroup) => $userGroup->userGroupId
                )
                ->all();
            // secure that user-specified user group IDs are from the right context
            $userGroupIds = array_intersect($this->getData('userGroupIds'), $allUserGroupIds);
            $mastheadUserGroupIds = array_intersect($this->getData('mastheadUserGroupIds'), $allUserGroupIds);

            // get current user group IDs for this context
            $oldUserGroupIds = UserGroup::query()
                ->withContextIds([$contextId])
                ->whereHas('userUserGroups', function ($query) {
                    $query->withUserId($this->userId)
                        ->withActive();
                })
                ->pluck('user_group_id')
                ->all();

            $userGroupsToEnd = array_diff($oldUserGroupIds, $userGroupIds);
            collect($userGroupsToEnd)
                ->each(
                    function ($userGroupId) use ($contextId) {
                        Repo::userGroup()->endAssignments($contextId, $this->userId, $userGroupId);
                    }
                );

            $userGroupsToAdd = array_diff($userGroupIds, $oldUserGroupIds);
            collect($userGroupsToAdd)
                ->each(
                    fn ($userGroupId) => Repo::userGroup()->assignUserToGroup($this->userId, $userGroupId)
                );

            // update masthead
            // ignore reviewer role
            $reviewerUserGroupIds = Repo::userGroup()->getArrayIdByRoleId(Role::ROLE_ID_REVIEWER, $contextId);
            collect($userGroupIds)
                ->filter(
                    function ($userGroupId) use ($reviewerUserGroupIds) {
                        return !in_array($userGroupId, $reviewerUserGroupIds);
                    }
                )
                ->each(
                    function ($userGroupId) use ($mastheadUserGroupIds) {
                        $masthead = match (in_array($userGroupId, $mastheadUserGroupIds)) {
                            true => UserUserGroupMastheadStatus::STATUS_ON,
                            false => UserUserGroupMastheadStatus::STATUS_OFF
                        };
                        Repo::userGroup()->updateUserUserGroupMastheadStatus($this->userId, $userGroupId, $masthead);
                    }
                );
        }
    }
}
