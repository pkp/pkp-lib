<?php

/**
 * @file controllers/grid/settings/roles/form/UserGroupForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserGroupForm
 * @ingroup controllers_grid_settings_roles_form
 *
 * @brief Form to add/edit user group.
 */

use APP\template\TemplateManager;

use PKP\core\JSONMessage;
use PKP\form\Form;
use PKP\facades\Locale;
use PKP\security\Role;
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
     * @param Context $contextId id.
     * @param User $userGroupId group id.
     */
    public function __construct($contextId, $userGroupId = null)
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
    public function getLocaleFieldNames()
    {
        return ['name', 'abbrev'];
    }

    /**
     * @copydoc Form::initData()
     */
    public function initData()
    {
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $userGroup = $userGroupDao->getById($this->getUserGroupId());
        $stages = WorkflowStageDAO::getWorkflowStageTranslationKeys();
        $this->setData('stages', $stages);
        $this->setData('assignedStages', []); // sensible default

        $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */
        $jsonMessage = new JSONMessage();
        $jsonMessage->setContent($roleDao->getForbiddenStages());
        $this->setData('roleForbiddenStagesJSON', $jsonMessage->getString());

        if ($userGroup) {
            $assignedStages = $userGroupDao->getAssignedStagesByUserGroupId($this->getContextId(), $userGroup->getId());

            $data = [
                'userGroupId' => $userGroup->getId(),
                'roleId' => $userGroup->getRoleId(),
                'name' => $userGroup->getName(null), //Localized
                'abbrev' => $userGroup->getAbbrev(null), //Localized
                'assignedStages' => array_keys($assignedStages),
                'showTitle' => $userGroup->getShowTitle(),
                'permitSelfRegistration' => $userGroup->getPermitSelfRegistration(),
                'permitMetadataEdit' => $userGroup->getPermitMetadataEdit(),
                'recommendOnly' => $userGroup->getRecommendOnly(),
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
        $this->readUserVars(['roleId', 'name', 'abbrev', 'assignedStages', 'showTitle', 'permitSelfRegistration', 'recommendOnly', 'permitMetadataEdit']);
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
        $templateMgr->assign('recommendOnlyRoleIds', $this->getRecommendOnlyRoles());
        $templateMgr->assign('notChangeMetadataEditPermissionRoles', UserGroupDAO::getNotChangeMetadataEditPermissionRoles());

        return parent::fetch($request, $template, $display);
    }

    /**
     * Get a list of roles optionally permitting user self-registration.
     *
     * @return array
     */
    public function getPermitSelfRegistrationRoles()
    {
        return [Role::ROLE_ID_REVIEWER, Role::ROLE_ID_AUTHOR, Role::ROLE_ID_READER];
    }

    /**
     * Get a list of roles optionally permitting recommendOnly option.
     *
     * @return array
     */
    public function getRecommendOnlyRoles()
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
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */

        // Check if we are editing an existing user group or creating another one.
        if ($userGroupId == null) {
            $userGroup = $userGroupDao->newDataObject();
            $userGroup->setRoleId($this->getData('roleId'));
            $userGroup->setContextId($this->getContextId());
            $userGroup->setDefault(false);
            $userGroup->setShowTitle($this->getData('showTitle'));
            $userGroup->setPermitSelfRegistration($this->getData('permitSelfRegistration') && in_array($userGroup->getRoleId(), $this->getPermitSelfRegistrationRoles()));
            $userGroup->setPermitMetadataEdit($this->getData('permitMetadataEdit') && !in_array($this->getData('roleId'), UserGroupDAO::getNotChangeMetadataEditPermissionRoles()));
            if (in_array($this->getData('roleId'), UserGroupDAO::getNotChangeMetadataEditPermissionRoles())) {
                $userGroup->setPermitMetadataEdit(true);
            }

            $userGroup->setRecommendOnly($this->getData('recommendOnly') && in_array($userGroup->getRoleId(), $this->getRecommendOnlyRoles()));
            $userGroup = $this->_setUserGroupLocaleFields($userGroup, $request);

            $userGroupId = $userGroupDao->insertObject($userGroup);
        } else {
            $userGroup = $userGroupDao->getById($userGroupId);
            $userGroup = $this->_setUserGroupLocaleFields($userGroup, $request);
            $userGroup->setShowTitle($this->getData('showTitle'));
            $userGroup->setPermitSelfRegistration($this->getData('permitSelfRegistration') && in_array($userGroup->getRoleId(), $this->getPermitSelfRegistrationRoles()));
            $userGroup->setPermitMetadataEdit($this->getData('permitMetadataEdit') && !in_array($userGroup->getRoleId(), UserGroupDAO::getNotChangeMetadataEditPermissionRoles()));
            if (in_array($userGroup->getRoleId(), UserGroupDAO::getNotChangeMetadataEditPermissionRoles())) {
                $userGroup->setPermitMetadataEdit(true);
            } else {
                $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
                $allUserAssignments = $stageAssignmentDao
                    ->getByUserGroupId($userGroupId, $this->getContextId())
                    ->toAssociativeArray();

                foreach ($allUserAssignments as $userAssignment) {
                    $userAssignment->setCanChangeMetadata($userGroup->getPermitMetadataEdit());
                    $stageAssignmentDao->updateObject($userAssignment);
                }
            }

            $userGroup->setRecommendOnly($this->getData('recommendOnly') && in_array($userGroup->getRoleId(), $this->getRecommendOnlyRoles()));

            $userGroupDao->updateObject($userGroup);
        }

        // After we have created/edited the user group, we assign/update its stages.
        $assignedStages = $this->getData('assignedStages');
        // Always set all stages active for some permission levels.
        if (in_array($userGroup->getRoleId(), $roleDao->getAlwaysActiveStages())) {
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
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */

        // Current existing workflow stages.
        $stages = WorkflowStageDAO::getWorkflowStageTranslationKeys();

        foreach (array_keys($stages) as $stageId) {
            $userGroupDao->removeGroupFromStage($contextId, $userGroupId, $stageId);
        }

        foreach ($userAssignedStages as $stageId) {

            // Make sure we don't assign forbidden stages based on
            // user groups role id. Override in case of some permission levels.
            $roleId = $this->getData('roleId');
            $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */
            $forbiddenStages = $roleDao->getForbiddenStages($roleId);
            if (in_array($stageId, $forbiddenStages) && !in_array($roleId, $roleDao->getAlwaysActiveStages())) {
                continue;
            }

            // Check if is a valid stage.
            if (in_array($stageId, array_keys($stages))) {
                $userGroupDao->assignGroupToStage($contextId, $userGroupId, $stageId);
            } else {
                fatalError('Invalid stage id');
            }
        }
    }

    /**
     * Set locale fields on a User Group object.
     *
     * @param UserGroup $userGroup
     * @param Request $request
     *
     * @return UserGroup
     */
    public function _setUserGroupLocaleFields($userGroup, $request)
    {
        $router = $request->getRouter();
        $context = $router->getContext($request);
        $supportedLocales = $context->getSupportedLocaleNames();

        if (!empty($supportedLocales)) {
            foreach ($context->getSupportedLocaleNames() as $localeKey => $localeName) {
                $name = $this->getData('name');
                $abbrev = $this->getData('abbrev');
                if (isset($name[$localeKey])) {
                    $userGroup->setName($name[$localeKey], $localeKey);
                }
                if (isset($abbrev[$localeKey])) {
                    $userGroup->setAbbrev($abbrev[$localeKey], $localeKey);
                }
            }
        } else {
            $localeKey = Locale::getLocale();
            $userGroup->setName($this->getData('name'), $localeKey);
            $userGroup->setAbbrev($this->getData('abbrev'), $localeKey);
        }

        return $userGroup;
    }
}
