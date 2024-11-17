<?php

/**
 * @file controllers/grid/settings/user/UserGridHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserGridHandler
 *
 * @ingroup controllers_grid_settings_user
 *
 * @brief Handle user grid requests.
 */

namespace PKP\controllers\grid\settings\user;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use PKP\controllers\grid\ColumnBasedGridCellProvider;
use PKP\controllers\grid\DataObjectGridCellProvider;
use PKP\controllers\grid\feature\PagingFeature;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\controllers\grid\settings\user\form\UserDetailsForm;
use PKP\controllers\grid\settings\user\form\UserDisableForm;
use PKP\controllers\grid\settings\user\form\UserEmailForm;
use PKP\controllers\grid\settings\user\form\UserRoleForm;
use PKP\core\JSONMessage;
use PKP\core\PKPRequest;
use PKP\core\VirtualArrayIterator;
use PKP\db\DAORegistry;
use PKP\identity\Identity;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\notification\Notification;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\Role;
use PKP\security\RoleDAO;
use PKP\security\Validation;
use PKP\user\User;
use PKP\userGroup\UserGroup;

class UserGridHandler extends GridHandler
{
    /** @var int user id for the user to remove */
    public $_oldUserId;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [
                Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
            ['fetchGrid', 'fetchRow', 'editUser', 'updateUser', 'updateUserRoles',
                'editDisableUser', 'disableUser', 'removeUser', 'addUser',
                'editEmail', 'sendEmail', 'mergeUsers']
        );
    }


    //
    // Implement template methods from PKPHandler.
    //
    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
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

        $this->_oldUserId = (int) $request->getUserVar('oldUserId');
        // Basic grid configuration.
        $this->setTitle('grid.user.currentUsers');

        // Grid actions.
        $router = $request->getRouter();

        $this->addAction(
            new LinkAction(
                'addUser',
                new AjaxModal(
                    $router->url($request, null, null, 'addUser', null, null),
                    __('grid.user.add'),
                    'side-modal',
                    true
                ),
                __('grid.user.add'),
                'add_user'
            )
        );

        //
        // Grid columns.
        //
        $cellProvider = new DataObjectGridCellProvider();

        // First Name.
        $this->addColumn(
            new GridColumn(
                'givenName',
                'user.givenName',
                null,
                null,
                $cellProvider
            )
        );

        // Last Name.
        $this->addColumn(
            new GridColumn(
                'familyName',
                'user.familyName',
                null,
                null,
                $cellProvider
            )
        );

        // User name.
        $this->addColumn(
            new GridColumn(
                'userName',
                'user.username',
                null,
                null,
                $cellProvider
            )
        );

        // Roles.
        $columnBasedGridCellProvider = new ColumnBasedGridCellProvider();
        $this->addColumn(
            new class (
                'roles',
                'user.roles',
                null,
                null,
                $columnBasedGridCellProvider
            ) extends GridColumn {
                public function getTemplateVarsFromRow($row): array
                {
                    $user = $row->getData();
                    assert($user instanceof User);
                    $contextId = Application::get()->getRequest()->getContext()->getId();
                    $userGroupsIterator = Repo::userGroup()->userUserGroups($user->getId(), $contextId);
                    $roles = $userGroupsIterator->map(fn (UserGroup $userGroup) => $userGroup->getLocalizedName())->join(__('common.commaListSeparator'));
                    return ['label' => $roles];
                }
            }
        );

        // Email.
        $this->addColumn(
            new GridColumn(
                'email',
                'user.email',
                null,
                null,
                $cellProvider
            )
        );
    }


    //
    // Implement methods from GridHandler.
    //
    /**
     * @copydoc GridHandler::getRowInstance()
     *
     * @return UserGridRow
     */
    protected function getRowInstance()
    {
        return new UserGridRow($this->_oldUserId);
    }

    /**
     * @copydoc GridHandler::initFeatures()
     */
    public function initFeatures($request, $args)
    {
        return [new PagingFeature()];
    }

    /**
     * @copydoc GridHandler::loadData()
     *
     * @param PKPRequest $request
     *
     * @return VirtualArrayIterator Grid data.
     */
    protected function loadData($request, $filter)
    {
        $context = $request->getContext();

        $collector = Repo::user()->getCollector();
        $collector->filterByStatus($collector::STATUS_ALL);
        if ($filter['userGroup'] ?? false) {
            $collector->filterByUserGroupIds((array) $filter['userGroup']);
        }
        if (!($filter['includeNoRole'] ?? false)) {
            $collector->filterByContextIds([$context->getId()]);
        }
        if (strlen($filter['search'] ?? '')) {
            $collector->searchPhrase($filter['search']);
        }

        // Handle grid paging (deprecated style)
        $rangeInfo = $this->getGridRangeInfo($request, $this->getId());
        $totalCount = $collector->getCount();
        $collector->limit($rangeInfo->getCount());
        $collector->offset($rangeInfo->getOffset() + max(0, $rangeInfo->getPage() - 1) * $rangeInfo->getCount());
        $iterator = $collector->getMany();
        return new VirtualArrayIterator(iterator_to_array($iterator, true), $totalCount, $rangeInfo->getPage(), $rangeInfo->getCount());
    }

    /**
     * @copydoc GridHandler::renderFilter()
     */
    public function renderFilter($request, $filterData = [])
    {
        $context = $request->getContext();

        $userGroups = Repo::userGroup()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->getMany();
        $userGroupOptions = ['' => __('grid.user.allRoles')];
        foreach ($userGroups as $userGroup) {
            $userGroupOptions[$userGroup->getId()] = $userGroup->getLocalizedName();
        }

        $userDao = Repo::user()->dao;
        $fieldOptions = [
            Identity::IDENTITY_SETTING_GIVENNAME => 'user.givenName',
            Identity::IDENTITY_SETTING_FAMILYNAME => 'user.familyName',
            $userDao::USER_FIELD_USERNAME => 'user.username',
            $userDao::USER_FIELD_EMAIL => 'user.email'
        ];

        $matchOptions = [
            'contains' => 'form.contains',
            'is' => 'form.is'
        ];

        $filterData = [
            'userGroupOptions' => $userGroupOptions,
            'fieldOptions' => $fieldOptions,
            'matchOptions' => $matchOptions,
            // oldUserId is used when merging users. see: userGridFilter.tpl
            'oldUserId' => $request->getUserVar('oldUserId'),
        ];

        return parent::renderFilter($request, $filterData);
    }

    /**
     * @copydoc GridHandler::getFilterSelectionData()
     *
     * @return array Filter selection data.
     */
    public function getFilterSelectionData($request)
    {
        // Get the search terms.
        $includeNoRole = $request->getUserVar('includeNoRole') ? (int) $request->getUserVar('includeNoRole') : null;
        $userGroup = $request->getUserVar('userGroup') ? (int)$request->getUserVar('userGroup') : null;
        $searchField = $request->getUserVar('searchField');
        $searchMatch = $request->getUserVar('searchMatch');
        $search = $request->getUserVar('search');

        return $filterSelectionData = [
            'includeNoRole' => $includeNoRole,
            'userGroup' => $userGroup,
            'searchField' => $searchField,
            'searchMatch' => $searchMatch,
            'search' => $search ? $search : ''
        ];
    }

    /**
     * @copydoc GridHandler::getFilterForm()
     *
     * @return string Filter template.
     */
    protected function getFilterForm()
    {
        return 'controllers/grid/settings/user/userGridFilter.tpl';
    }

    /**
     * Get the js handler for this component.
     *
     * @return string
     */
    public function getJSHandler()
    {
        return '$.pkp.controllers.grid.users.UserGridHandler';
    }


    //
    // Public grid actions.
    //
    /**
     * Add a new user.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function addUser($args, $request)
    {
        // Calling editUser with an empty row id will add a new user.
        return $this->editUser($args, $request);
    }

    /**
     * Edit an existing user.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function editUser($args, $request)
    {
        // Identify the user Id.
        $userId = $request->getUserVar('rowId');

        if (!$userId) {
            $userId = $request->getUserVar('userId');
        }

        $user = $request->getUser();
        $administrationLevel = null;

        if ($userId !== null && ($administrationLevel = Validation::getAdministrationLevel($userId, $user->getId(), $request->getContext()->getId())) === Validation::ADMINISTRATION_PROHIBITED) {
            // We don't have administrative rights over this user.
            return new JSONMessage(false, __('grid.user.cannotAdminister'));
        }

        // Form handling.
        $userForm = new UserDetailsForm($request, $userId);

        $administrationLevel === Validation::ADMINISTRATION_PARTIAL
            ? $userForm->applyUserGroupUpdateOnly()
            : $userForm->attachValidationChecks($request);

        $userForm->initData();

        return new JSONMessage(true, $userForm->display($request));
    }

    /**
     * Update an existing user.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function updateUser($args, $request)
    {
        $user = $request->getUser();

        // Identify the user Id.
        $userId = $request->getUserVar('userId');
        $administrationLevel = null;

        if ($userId !== null && ($administrationLevel = Validation::getAdministrationLevel($userId, $user->getId(), $request->getContext()->getId())) === Validation::ADMINISTRATION_PROHIBITED) {
            // We don't have administrative rights over this user.
            return new JSONMessage(false, __('grid.user.cannotAdminister'));
        }

        // Form handling.
        $userForm = new UserDetailsForm($request, $userId);

        $administrationLevel === Validation::ADMINISTRATION_PARTIAL
            ? $userForm->applyUserGroupUpdateOnly()
            : $userForm->attachValidationChecks($request);

        $userForm->readInputData();

        if ($userForm->validate()) {
            $user = $userForm->execute();

            // If this is a newly created user, show role management form.
            if (!$userId) {
                $userRoleForm = new UserRoleForm($user->getId(), $user->getFullName());
                $userRoleForm->initData();
                return new JSONMessage(true, $userRoleForm->display($request));
            } else {
                // Successful edit of an existing user.
                $notificationManager = new NotificationManager();
                $user = $request->getUser();
                $notificationManager->createTrivialNotification($user->getId(), Notification::NOTIFICATION_TYPE_SUCCESS, ['contents' => __('notification.editedUser')]);

                // Prepare the grid row data.
                return \PKP\db\DAO::getDataChangedEvent($userId);
            }
        } else {
            return new JSONMessage(false);
        }
    }

    /**
     * Update a newly created user's roles
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function updateUserRoles($args, $request)
    {
        $user = $request->getUser();

        // Identify the user Id.
        $userId = $request->getUserVar('userId');

        if ($userId !== null && Validation::getAdministrationLevel($userId, $user->getId()) !== Validation::ADMINISTRATION_FULL) {
            // We don't have administrative rights over this user.
            return new JSONMessage(false, __('grid.user.cannotAdminister'));
        }

        // Form handling.
        $userRoleForm = new UserRoleForm($userId, $user->getFullName());
        $userRoleForm->readInputData();

        if ($userRoleForm->validate()) {
            $userRoleForm->execute();

            // Successfully managed newly created user's roles.
            return \PKP\db\DAO::getDataChangedEvent();
        } else {
            return new JSONMessage(false);
        }
    }

    /**
     * Edit enable/disable user form
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage Serialized JSON object
     */
    public function editDisableUser($args, $request)
    {
        $user = $request->getUser();

        // Identify the user Id.
        $userId = $request->getUserVar('rowId');
        if (!$userId) {
            $userId = $request->getUserVar('userId');
        }

        // Are we enabling or disabling this user.
        $enable = isset($args['enable']) ? (bool) $args['enable'] : false;

        if ($userId !== null && Validation::getAdministrationLevel($userId, $user->getId()) !== Validation::ADMINISTRATION_FULL) {
            // We don't have administrative rights over this user.
            return new JSONMessage(false, __('grid.user.cannotAdminister'));
        } else {
            // Form handling
            $userForm = new UserDisableForm($userId, $enable);

            $userForm->initData();

            return new JSONMessage(true, $userForm->display($request));
        }
    }

    /**
     * Enable/Disable an existing user
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function disableUser($args, $request)
    {
        $user = $request->getUser();

        // Identify the user Id.
        $userId = $request->getUserVar('userId');

        // Are we enabling or disabling this user.
        $enable = (bool) $request->getUserVar('enable');

        if ($userId !== null && Validation::getAdministrationLevel($userId, $user->getId()) !== Validation::ADMINISTRATION_FULL) {
            // We don't have administrative rights over this user.
            return new JSONMessage(false, __('grid.user.cannotAdminister'));
        }

        // Form handling.
        $userForm = new UserDisableForm($userId, $enable);

        $userForm->readInputData();

        if ($userForm->validate()) {
            $user = $userForm->execute();

            // Successful enable/disable of an existing user.
            // Update grid data.
            return \PKP\db\DAO::getDataChangedEvent($userId);
        } else {
            return new JSONMessage(false, $userForm->display($request));
        }
    }

    /**
     * Remove all user group assignments for a context for a given user.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function removeUser($args, $request)
    {
        if (!$request->checkCSRF()) {
            return new JSONMessage(false);
        }

        $context = $request->getContext();
        $user = $request->getUser();

        // Identify the user Id.
        $userId = $request->getUserVar('rowId');

        if ($userId !== null && Validation::getAdministrationLevel($userId, $user->getId(), $request->getContext()->getId()) === Validation::ADMINISTRATION_PROHIBITED) {
            // We don't have administrative rights over this user.
            return new JSONMessage(false, __('grid.user.cannotAdminister'));
        }

        // End all active user group assignments for this context.
        // Check if this user has any active user group assignments for this context.
        $activeUserGroupCount = Repo::userGroup()
            ->userUserGroups($userId, $context->getId())
            ->count();

        if (!$activeUserGroupCount) {
            return new JSONMessage(false, __('grid.user.userNoRoles'));
        } else {
            Repo::userGroup()->endAssignments($context->getId(), $userId);

            return \PKP\db\DAO::getDataChangedEvent($userId);
        }
    }

    /**
     * Displays a modal to edit an email message to the user.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage Serialized JSON object
     */
    public function editEmail($args, $request)
    {
        $user = $request->getUser();
        $context = $request->getContext();

        // Identify the user Id.
        $userId = $request->getUserVar('rowId');

        $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */
        if (
            !$roleDao->userHasRole(\PKP\core\PKPApplication::SITE_CONTEXT_ID, $user->getId(), Role::ROLE_ID_SITE_ADMIN) && !(
                $context &&
                $roleDao->userHasRole($context->getId(), $user->getId(), Role::ROLE_ID_MANAGER)
            )
        ) {
            // We don't have administrative rights over this user.
            return new JSONMessage(false, __('grid.user.cannotAdminister'));
        } else {
            // Form handling.
            $userEmailForm = new UserEmailForm($userId);
            $userEmailForm->initData();

            return new JSONMessage(true, $userEmailForm->fetch($request));
        }
    }

    /**
     * Send the user email and close the modal.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function sendEmail($args, $request)
    {
        $user = $request->getUser();
        $context = $request->getContext();

        // Identify the user Id.
        $userId = $request->getUserVar('userId');

        $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */
        if (
            !$roleDao->userHasRole(\PKP\core\PKPApplication::SITE_CONTEXT_ID, $user->getId(), Role::ROLE_ID_SITE_ADMIN) && !(
                $context &&
                $roleDao->userHasRole($context->getId(), $user->getId(), Role::ROLE_ID_MANAGER)
            )
        ) {
            // We don't have administrative rights over this user.
            return new JSONMessage(false, __('grid.user.cannotAdminister'));
        }
        // Form handling.
        $userEmailForm = new UserEmailForm($userId);
        $userEmailForm->readInputData();

        if ($userEmailForm->validate()) {
            $userEmailForm->execute();
            return new JSONMessage(true);
        } else {
            return new JSONMessage(false, __('validator.filled'));
        }
    }

    /**
     * Allow user account merging, including attributed submissions etc.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function mergeUsers($args, $request)
    {
        $newUserId = (int) $request->getUserVar('newUserId');
        $oldUserId = (int) $request->getUserVar('oldUserId');
        $user = $request->getUser();

        // if there is a $newUserId, this is the second time through, so merge the users.
        if ($newUserId > 0 && $oldUserId > 0 && Validation::getAdministrationLevel($oldUserId, $user->getId()) === Validation::ADMINISTRATION_FULL) {
            if (!$request->checkCSRF()) {
                return new JSONMessage(false);
            }
            Repo::user()->mergeUsers($oldUserId, $newUserId);
            $json = new JSONMessage(true);
            $json->setGlobalEvent('userMerged', [
                'oldUserId' => $oldUserId,
                'newUserId' => $newUserId,
            ]);
            return $json;

            // Otherwise present the grid for selecting the user to merge into
        } else {
            $userGrid = new UserGridHandler();
            $userGrid->initialize($request);
            $userGrid->setTitle('grid.user.mergeUsers.mergeIntoUser');
            return $userGrid->fetchGrid($args, $request);
        }
    }

    /**
     * @see GridHandler::getRequestArgs()
     */
    public function getRequestArgs()
    {
        $requestArgs = parent::getRequestArgs();
        $requestArgs['oldUserId'] = $this->_oldUserId;
        return $requestArgs;
    }
}
