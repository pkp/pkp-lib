<?php

/**
 * @file controllers/grid/settings/roles/UserGroupGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserGroupGridHandler
 * @ingroup controllers_grid_settings
 *
 * @brief Handle operations for user group management operations.
 */

use APP\notification\NotificationManager;

use PKP\controllers\grid\feature\PagingFeature;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\core\JSONMessage;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\notification\PKPNotification;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\internal\WorkflowStageRequiredPolicy;
use PKP\security\Role;

use PKP\workflow\WorkflowStageDAO;

class UserGroupGridHandler extends GridHandler
{
    /** @var int Context id. */
    private $_contextId;

    /** @var UserGroup User group object handled by some grid operations. */
    private $_userGroup;


    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER],
            [
                'fetchGrid',
                'fetchCategory',
                'fetchRow',
                'addUserGroup',
                'editUserGroup',
                'updateUserGroup',
                'removeUserGroup',
                'assignStage',
                'unassignStage'
            ]
        );
    }

    //
    // Overridden methods from PKPHandler.
    //
    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        $operation = $request->getRequestedOp();
        $workflowStageRequiredOps = ['assignStage', 'unassignStage'];
        if (in_array($operation, $workflowStageRequiredOps)) {
            $this->addPolicy(new WorkflowStageRequiredPolicy($request->getUserVar('stageId')));
        }

        $userGroupRequiredOps = array_merge($workflowStageRequiredOps, ['editUserGroup', 'removeUserGroup']);
        if (in_array($operation, $userGroupRequiredOps)) {
            // Validate the user group object.
            $userGroupId = $request->getUserVar('userGroupId');
            $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
            $userGroup = $userGroupDao->getById($userGroupId);

            if (!$userGroup) {
                fatalError('Invalid user group id!');
            } else {
                $this->_userGroup = $userGroup;
            }
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * @copydoc GridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        $context = $request->getContext();
        $this->_contextId = $context->getId();

        // Basic grid configuration.
        $this->setTitle('grid.roles.currentRoles');

        // Add grid-level actions.
        $router = $request->getRouter();
        $this->addAction(
            new LinkAction(
                'addUserGroup',
                new AjaxModal(
                    $router->url($request, null, null, 'addUserGroup'),
                    __('grid.roles.add'),
                    'modal_add_role'
                ),
                __('grid.roles.add'),
                'add_role'
            )
        );

        import('lib.pkp.controllers.grid.settings.roles.UserGroupGridCellProvider');
        $cellProvider = new UserGroupGridCellProvider();

        $workflowStagesLocales = WorkflowStageDAO::getWorkflowStageTranslationKeys();

        // Set array containing the columns info with the same cell provider.
        $columnsInfo = [
            1 => ['id' => 'name', 'title' => 'settings.roles.roleName', 'template' => null],
            2 => ['id' => 'roleId', 'title' => 'settings.roles.from', 'template' => null]
        ];

        foreach ($workflowStagesLocales as $stageId => $stageTitleKey) {
            $columnsInfo[] = ['id' => $stageId, 'title' => $stageTitleKey, 'template' => 'controllers/grid/common/cell/selectStatusCell.tpl'];
        }

        // Add array columns to the grid.
        foreach ($columnsInfo as $columnInfo) {
            $this->addColumn(
                new GridColumn(
                    $columnInfo['id'],
                    $columnInfo['title'],
                    null,
                    $columnInfo['template'],
                    $cellProvider
                )
            );
        }
    }

    /**
     * @copydoc GridHandler::loadData()
     */
    protected function loadData($request, $filter)
    {
        $contextId = $this->_getContextId();
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */

        $roleIdFilter = null;
        $stageIdFilter = null;

        if (!is_array($filter)) {
            $filter = [];
        }

        if (isset($filter['selectedRoleId'])) {
            $roleIdFilter = $filter['selectedRoleId'];
        }

        if (isset($filter['selectedStageId'])) {
            $stageIdFilter = $filter['selectedStageId'];
        }

        $rangeInfo = $this->getGridRangeInfo($request, $this->getId());

        if ($stageIdFilter && $stageIdFilter != 0) {
            return $userGroupDao->getUserGroupsByStage($contextId, $stageIdFilter, $roleIdFilter, $rangeInfo);
        } elseif ($roleIdFilter && $roleIdFilter != 0) {
            return $userGroupDao->getByRoleId($contextId, $roleIdFilter, false, $rangeInfo);
        } else {
            return $userGroupDao->getByContextId($contextId, $rangeInfo);
        }
    }

    /**
     * @copydoc GridHandler::getRowInstance()
     *
     * @return UserGroupGridRow
     */
    protected function getRowInstance()
    {
        import('lib.pkp.controllers.grid.settings.roles.UserGroupGridRow');
        return new UserGroupGridRow();
    }

    /**
    * @see GridHandler::renderFilter()
    */
    public function renderFilter($request, $filterData = [])
    {
        // Get filter data.
        $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */
        $roleOptions = [0 => 'grid.user.allPermissionLevels'] + Application::getRoleNames(true);

        // Reader roles are not important for stage assignments.
        if (array_key_exists(Role::ROLE_ID_READER, $roleOptions)) {
            unset($roleOptions[Role::ROLE_ID_READER]);
        }

        $filterData = ['roleOptions' => $roleOptions];

        $workflowStages = [0 => 'grid.userGroup.allStages'] + WorkflowStageDAO::getWorkflowStageTranslationKeys();
        $filterData['stageOptions'] = $workflowStages;

        return parent::renderFilter($request, $filterData);
    }

    /**
     * @see GridHandler::getFilterSelectionData()
     *
     * @return array Filter selection data.
     */
    public function getFilterSelectionData($request)
    {
        $selectedRoleId = $request->getUserVar('selectedRoleId');
        $selectedStageId = $request->getUserVar('selectedStageId');

        // Cast or set to grid filter default value (all roles).
        $selectedRoleId = (is_null($selectedRoleId) ? 0 : (int)$selectedRoleId);
        $selectedStageId = (is_null($selectedStageId) ? 0 : (int)$selectedStageId);

        return ['selectedRoleId' => $selectedRoleId, 'selectedStageId' => $selectedStageId];
    }

    /**
     * @see GridHandler::getFilterForm()
     *
     * @return string Filter template.
     */
    protected function getFilterForm()
    {
        return 'controllers/grid/settings/roles/userGroupsGridFilter.tpl';
    }

    /**
     * @see GridHandler::initFeatures()
     */
    public function initFeatures($request, $args)
    {
        return [new PagingFeature()];
    }


    //
    // Handler operations.
    //
    /**
     * Handle the add user group operation.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function addUserGroup($args, $request)
    {
        return $this->editUserGroup($args, $request);
    }

    /**
     * Handle the edit user group operation.
     *
     * @param array $args
     *
     * @return JSONMessage JSON object
     */
    public function editUserGroup($args, $request)
    {
        $userGroupForm = $this->_getUserGroupForm($request);

        $userGroupForm->initData();

        return new JSONMessage(true, $userGroupForm->fetch($request));
    }

    /**
     * Update user group data on database and grid.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function updateUserGroup($args, $request)
    {
        $userGroupForm = $this->_getUserGroupForm($request);

        $userGroupForm->readInputData();
        if ($userGroupForm->validate()) {
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification($request->getUser()->getId());
            $userGroupForm->execute();
            $json = \PKP\db\DAO::getDataChangedEvent();
            $json->setGlobalEvent('userGroupUpdated');
            return $json;
        } else {
            return new JSONMessage(true, $userGroupForm->fetch($request));
        }
    }

    /**
     * Remove user group.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function removeUserGroup($args, $request)
    {
        if (!$request->checkCSRF()) {
            return new JSONMessage(false);
        }

        $user = $request->getUser();
        $userGroup = $this->_userGroup;
        $contextId = $this->_getContextId();
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $notificationMgr = new NotificationManager();

        $usersAssignedToUserGroupCount = $userGroupDao->getContextUsersCount($contextId, $userGroup->getId());
        if ($usersAssignedToUserGroupCount == 0) {
            if ($userGroupDao->isDefault($userGroup->getId())) {
                // Can't delete default user groups.
                $notificationMgr->createTrivialNotification(
                    $user->getId(),
                    PKPNotification::NOTIFICATION_TYPE_WARNING,
                    ['contents' => __(
                        'grid.userGroup.cantRemoveDefaultUserGroup',
                        ['userGroupName' => $userGroup->getLocalizedName()	]
                    )]
                );
            } else {
                // We can delete, no user assigned yet.
                $userGroupDao->deleteObject($userGroup);
                $notificationMgr->createTrivialNotification(
                    $user->getId(),
                    PKPNotification::NOTIFICATION_TYPE_SUCCESS,
                    ['contents' => __(
                        'grid.userGroup.removed',
                        ['userGroupName' => $userGroup->getLocalizedName()	]
                    )]
                );
            }
        } else {
            // Can't delete while an user
            // is still assigned to that user group.
            $notificationMgr->createTrivialNotification(
                $user->getId(),
                PKPNotification::NOTIFICATION_TYPE_WARNING,
                ['contents' => __(
                    'grid.userGroup.cantRemoveUserGroup',
                    ['userGroupName' => $userGroup->getLocalizedName(), 'usersCount' => $usersAssignedToUserGroupCount]
                )]
            );
        }

        $json = \PKP\db\DAO::getDataChangedEvent($userGroup->getId());
        $json->setGlobalEvent('userGroupUpdated');
        return $json;
    }

    /**
     * Assign stage to user group.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function assignStage($args, $request)
    {
        return $this->_toggleAssignment($args, $request);
    }

    /**
    * Unassign stage to user group.
    *
    * @param array $args
    * @param PKPRequest $request
    */
    public function unassignStage($args, $request)
    {
        return $this->_toggleAssignment($args, $request);
    }

    //
    // Private helper methods.
    //

    /**
     * Toggle user group stage assignment.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    private function _toggleAssignment($args, $request)
    {
        if (!$request->checkCSRF()) {
            return new JSONMessage(false);
        }
        $userGroup = $this->_userGroup;
        $stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
        $contextId = $this->_getContextId();
        $operation = $request->getRequestedOp();

        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */

        switch ($operation) {
            case 'assignStage':
                $userGroupDao->assignGroupToStage($contextId, $userGroup->getId(), $stageId);
                $messageKey = 'grid.userGroup.assignedStage';
                break;
            case 'unassignStage':
                $userGroupDao->removeGroupFromStage($contextId, $userGroup->getId(), $stageId);
                $messageKey = 'grid.userGroup.unassignedStage';
                break;
        }

        $notificationMgr = new NotificationManager();
        $user = $request->getUser();

        $stageLocaleKeys = WorkflowStageDAO::getWorkflowStageTranslationKeys();

        $notificationMgr->createTrivialNotification(
            $user->getId(),
            PKPNotification::NOTIFICATION_TYPE_SUCCESS,
            ['contents' => __(
                $messageKey,
                ['userGroupName' => $userGroup->getLocalizedName(), 'stageName' => __($stageLocaleKeys[$stageId])]
            )]
        );

        return \PKP\db\DAO::getDataChangedEvent($userGroup->getId());
    }

    /**
     * Get a UserGroupForm instance.
     *
     * @param Request $request
     *
     * @return UserGroupForm
     */
    private function _getUserGroupForm($request)
    {
        // Get the user group Id.
        $userGroupId = (int) $request->getUserVar('userGroupId');

        // Instantiate the files form.
        import('lib.pkp.controllers.grid.settings.roles.form.UserGroupForm');
        $contextId = $this->_getContextId();
        return new UserGroupForm($contextId, $userGroupId);
    }

    /**
     * Get context id.
     *
     * @return int
     */
    private function _getContextId()
    {
        return $this->_contextId;
    }
}
