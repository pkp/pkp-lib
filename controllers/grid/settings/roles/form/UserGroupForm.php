<?php

/**
 * @file controllers/grid/settings/roles/form/UserGroupForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserGroupForm
 *
 * @ingroup controllers_grid_settings_roles_form
 *
 * @brief Form to add/edit user group.
 */

namespace PKP\controllers\grid\settings\roles\form;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\core\JSONMessage;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\form\Form;
use PKP\security\Role;
use PKP\security\RoleDAO;
use PKP\stageAssignment\StageAssignment;
use PKP\userGroup\relationships\UserGroupStage;
use PKP\userGroup\UserGroup;
use PKP\workflow\WorkflowStageDAO;

class UserGroupForm extends Form
{
    /** @var int Id of the user group being edited */
    public $_userGroupId;

    /** @var int The context of the user group being edited */
    public $_contextId;


    /**
     * Constructor.
     *
     * @param int $contextId id.
     * @param int $userGroupId group id.
     */
    public function __construct(int $contextId, $userGroupId = null)
    {
        parent::__construct('controllers/grid/settings/roles/form/userGroupForm.tpl');
        $this->_contextId = $contextId;
        $this->_userGroupId = $userGroupId;

        // Validation checks for this form
        $this->addCheck(new \PKP\form\validation\FormValidatorLocale($this, 'name', 'required', 'settings.roles.nameRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidatorLocale($this, 'abbrev', 'required', 'settings.roles.abbrevRequired'));
        if ($this->getUserGroupId() == null) {
            $this->addCheck(new \PKP\form\validation\FormValidator($this, 'roleId', 'required', 'settings.roles.roleIdRequired'));
        }
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }

    //
    // Getters and Setters
    //
    /**
     * Get the user group id.
     *
     * @return int userGroupId
     */
    public function getUserGroupId()
    {
        return $this->_userGroupId;
    }

    /**
     * Get the context id.
     *
     * @return int contextId
     */
    public function getContextId()
    {
        return $this->_contextId;
    }

    //
    // Implement template methods from Form.
    //
    /**
     * Get all locale field names
     */
    public function getLocaleFieldNames(): array
    {
        return ['name', 'abbrev'];
    }

    /**
     * @copydoc Form::initData()
     */
    public function initData()
    {
        $userGroup = UserGroup::findById($this->getUserGroupId(), $this->getContextId());
        $stages = WorkflowStageDAO::getWorkflowStageTranslationKeys();
        $this->setData('stages', $stages);
        $this->setData('assignedStages', []); // sensible default

        $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */
        $jsonMessage = new JSONMessage();
        $jsonMessage->setContent($roleDao->getForbiddenStages());
        $this->setData('roleForbiddenStagesJSON', $jsonMessage->getString());

        if ($userGroup) {
            $assignedStages = $userGroup->getAssignedStageIds()->toArray();
            // Get a list of all settings-accessible user groups for the current user in
            // order to prevent them from locking themselves out by disabling the only one.
            $mySettingsAccessUserGroupIds = UserGroup::withContextIds([$this->getContextId()])
                ->withUserIds([Application::get()->getRequest()->getUser()->getId()])
                ->get()
                ->filter(fn ($userGroup) => $userGroup->permitSettings)
                ->map(fn ($userGroup) => $userGroup->id)
                ->all();

            $data = [
                'userGroupId' => $userGroup->id,
                'roleId' => $userGroup->roleId,
                'name' => $userGroup->name, // Localized array
                'abbrev' => $userGroup->abbrev, // Localized array
                'assignedStages' => $assignedStages,
                'showTitle' => $userGroup->showTitle,
                'mySettingsAccessUserGroupIds' => array_values($mySettingsAccessUserGroupIds),
                'permitSelfRegistration' => $userGroup->permitSelfRegistration,
                'permitMetadataEdit' => $userGroup->permitMetadataEdit,
                'permitSettings' => $userGroup->permitSettings,
                'recommendOnly' => $userGroup->recommendOnly,
                'masthead' => $userGroup->masthead,
            ];

            foreach ($data as $field => $value) {
                $this->setData($field, $value);
            }
        }
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['roleId', 'name', 'abbrev', 'assignedStages', 'showTitle', 'permitSelfRegistration', 'recommendOnly', 'permitMetadataEdit', 'permitSettings', 'masthead']);
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);

        $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */
        $templateMgr->assign('roleOptions', Application::getRoleNames(true));

        // Users can't edit the role once user group is created.
        // userGroupId is 0 for new User Groups because it is cast to int in UserGroupGridHandler.
        $disableRoleSelect = ($this->getUserGroupId() > 0) ? true : false;
        $templateMgr->assign('disableRoleSelect', $disableRoleSelect);
        $templateMgr->assign('selfRegistrationRoleIds', $this->getPermitSelfRegistrationRoles());
        $templateMgr->assign('permitSettingsRoleIds', $this->getPermitSettingsRoles());
        $templateMgr->assign('recommendOnlyRoleIds', $this->getRecommendOnlyRoles());
        $repository = Repo::userGroup();
        $templateMgr->assign('notChangeMetadataEditPermissionRoles', $repository::NOT_CHANGE_METADATA_EDIT_PERMISSION_ROLES);
        return parent::fetch($request, $template, $display);
    }

    /**
     * Get a list of roles optionally permitting user self-registration.
     */
    public function getPermitSelfRegistrationRoles(): array
    {
        return [Role::ROLE_ID_REVIEWER, Role::ROLE_ID_AUTHOR, Role::ROLE_ID_READER];
    }

    /**
     * Get a list of roles optionally permitting settings access.
     */
    public function getPermitSettingsRoles(): array
    {
        return [Role::ROLE_ID_MANAGER];
    }

    /**
     * Get a list of roles optionally permitting recommendOnly option.
     */
    public function getRecommendOnlyRoles(): array
    {
        return [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR];
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionParams)
    {
        parent::execute(...$functionParams);

        $request = Application::get()->getRequest();
        $userGroupId = $this->getUserGroupId();
        $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */

        $repository = Repo::userGroup();

        // Check if we are editing an existing user group or creating another one.
        if ($userGroupId == null) {
            // creating a new UserGroup
            $userGroup = new UserGroup();

            $roleId = $this->getData('roleId');
            if ($roleId == Role::ROLE_ID_SITE_ADMIN) {
                throw new \Exception('Site administrator roles cannot be created here.');
            }
            $userGroup->roleId = $roleId;

            $userGroup->contextId = $this->getContextId();
            $userGroup->isDefault = false;
            $userGroup->showTitle = (bool) $this->getData('showTitle');
            $userGroup->permitSelfRegistration = $this->getData('permitSelfRegistration') && in_array($userGroup->roleId, $this->getPermitSelfRegistrationRoles());
            $userGroup->permitSettings = $this->getData('permitSettings') && $userGroup->roleId == Role::ROLE_ID_MANAGER;

            if (in_array($userGroup->roleId, Repo::userGroup()::NOT_CHANGE_METADATA_EDIT_PERMISSION_ROLES)) {
                $userGroup->permitMetadataEdit = true;
            } else {
                $userGroup->permitMetadataEdit = (bool) $this->getData('permitMetadataEdit');
            }

            $userGroup->recommendOnly = $this->getData('recommendOnly') && in_array($userGroup->roleId, $this->getRecommendOnlyRoles());
            $userGroup->masthead = (bool) $this->getData('masthead');

            // set localized fields
            $userGroup = $this->_setUserGroupLocaleFields($userGroup, $request);

            // save the user group
            $userGroup->save();
            $userGroupId = $userGroup->id;
        } else {
            // editing an existing UserGroup
            $userGroup = UserGroup::findById($userGroupId, $this->getContextId());

            // update localized fields
            $userGroup = $this->_setUserGroupLocaleFields($userGroup, $request);
            $userGroup->permitSettings = $this->getData('permitSettings') && $userGroup->roleId == Role::ROLE_ID_MANAGER;
            $userGroup->showTitle = (bool) $this->getData('showTitle');
            $userGroup->permitSelfRegistration = $this->getData('permitSelfRegistration') && in_array($userGroup->roleId, $this->getPermitSelfRegistrationRoles());

            $previousPermitMetadataEdit = $userGroup->permitMetadataEdit;

            if (in_array($userGroup->roleId, $repository::NOT_CHANGE_METADATA_EDIT_PERMISSION_ROLES)) {
                $userGroup->permitMetadataEdit = true;
            } else {
                $userGroup->permitMetadataEdit = (bool) $this->getData('permitMetadataEdit');
            }

            // if permitMetadataEdit has changed, update StageAssignments
            if ($userGroup->permitMetadataEdit !== $previousPermitMetadataEdit) {
                $stageAssignments = StageAssignment::query()
                    ->withUserGroupId($userGroupId)
                    ->withContextId($this->getContextId())
                    ->get();

                foreach ($stageAssignments as $stageAssignment) {
                    $stageAssignment->canChangeMetadata = $userGroup->permitMetadataEdit;
                    $stageAssignment->save();
                }
            }

            $userGroup->recommendOnly = $this->getData('recommendOnly') && in_array($userGroup->roleId, $this->getRecommendOnlyRoles());
            $userGroup->masthead = (bool) $this->getData('masthead');
            $userGroup->save();
        }

        // After we have created/edited the user group, we assign/update its stages.
        $assignedStages = $this->getData('assignedStages');

        // Always set all stages active for some permission levels.
        if (in_array($userGroup->roleId, $roleDao->getAlwaysActiveStages())) {
            $assignedStages = array_keys(WorkflowStageDAO::getWorkflowStageTranslationKeys());
        }
        if ($assignedStages) {
            $this->_assignStagesToUserGroup($userGroupId, $assignedStages);
        }
    }


    //
    // Private helper methods
    //
    /**
     * Setup the stages assignments to a user group in database.
     *
     * @param int $userGroupId User group id that will receive the stages.
     * @param array $userAssignedStages of stages currently assigned to a user.
     */
    public function _assignStagesToUserGroup($userGroupId, $userAssignedStages)
    {
        $contextId = $this->getContextId();
        $roleId = $this->getData('roleId');
        $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */


        // Current existing workflow stages.
        $stages = WorkflowStageDAO::getWorkflowStageTranslationKeys();

        // Remove all existing stage assignments for this user group
        UserGroupStage::query()
            ->withContextId($contextId)
            ->withUserGroupId($userGroupId)
            ->delete();

        // Assign new stages
        foreach ($userAssignedStages as $stageId) {
            // Make sure we don't assign forbidden stages based on user group role id
            $forbiddenStages = $roleDao->getForbiddenStages($roleId);
            if (in_array($stageId, $forbiddenStages) && !in_array($roleId, $roleDao->getAlwaysActiveStages())) {
                continue;
            }

            // Check if it's a valid stage.
            if (array_key_exists($stageId, $stages)) {
                UserGroupStage::create([
                    'contextId' => $contextId,
                    'userGroupId' => $userGroupId,
                    'stageId' => $stageId,
                ]);
            } else {
                throw new \Exception('Invalid stage id');
            }
        }
    }

    /**
     * Set locale fields on a User Group object.
     *
     * @param \PKP\userGroup\UserGroup $userGroup
     * @param Request $request
     *
     */
    public function _setUserGroupLocaleFields($userGroup, $request): \PKP\userGroup\UserGroup
    {
        $router = $request->getRouter();
        $context = $router->getContext($request);
        $supportedLocales = $context->getSupportedLocales();
        $name = $this->getData('name');
        $abbrev = $this->getData('abbrev');
        $userGroupNames = $userGroup->name;
        $userGroupAbbrevs = $userGroup->abbrev;

        if (!empty($supportedLocales)) {
            foreach ($supportedLocales as $localeKey) {
                if (isset($name[$localeKey])) {
                    $userGroupNames[$localeKey] = $name[$localeKey];
                }
                if (isset($abbrev[$localeKey])) {
                    $userGroupAbbrevs[$localeKey] = $abbrev[$localeKey];
                }
            }
        } else {
            $localeKey = Locale::getLocale();
            $userGroupNames[$localeKey] = $name[$localeKey] ?? '';
            $userGroupAbbrevs[$localeKey] = $abbrev[$localeKey] ?? '';
        }

        $userGroup->name = $userGroupNames;
        $userGroup->abbrev = $userGroupAbbrevs;

        return $userGroup;
    }
}
