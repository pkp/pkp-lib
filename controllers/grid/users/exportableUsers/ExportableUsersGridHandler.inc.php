<?php

/**
 * @file controllers/grid/users/exportableUsers/ExportableUsersGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ExportableUsersGridHandler
 * @ingroup controllers_grid_users_exportableUsers
 *
 * @brief Handle exportable user grid requests.
 */

use APP\facades\Repo;
use PKP\controllers\grid\DataObjectGridCellProvider;
use PKP\controllers\grid\feature\PagingFeature;
use PKP\controllers\grid\feature\selectableItems\SelectableItemsFeature;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\identity\Identity;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\RedirectConfirmationModal;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\Role;

class ExportableUsersGridHandler extends GridHandler
{
    public $_pluginName;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [
                Role::ROLE_ID_MANAGER],
            ['fetchGrid', 'fetchRow']
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

        // Basic grid configuration.
        $this->setTitle('grid.user.currentUsers');

        // Grid actions.
        $router = $request->getRouter();
        $pluginName = $request->getUserVar('pluginName');
        assert(!empty($pluginName));
        $this->_pluginName = $pluginName;

        $dispatcher = $request->getDispatcher();
        $url = $dispatcher->url($request, PKPApplication::ROUTE_PAGE, null, 'management', 'importexport', ['plugin', $pluginName, 'exportAllUsers']);

        $this->addAction(
            new LinkAction(
                'exportAllUsers',
                new RedirectConfirmationModal(
                    __('grid.users.confirmExportAllUsers'),
                    null,
                    $url
                ),
                __('grid.action.exportAllUsers'),
                'export_users'
            )
        );

        //
        // Grid columns.
        //

        // First Name.
        $cellProvider = new DataObjectGridCellProvider();
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
        $cellProvider = new DataObjectGridCellProvider();
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
        $cellProvider = new DataObjectGridCellProvider();
        $this->addColumn(
            new GridColumn(
                'username',
                'user.username',
                null,
                null,
                $cellProvider
            )
        );

        // Email.
        $cellProvider = new DataObjectGridCellProvider();
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
     * @copydoc GridHandler::initFeatures()
     */
    public function initFeatures($request, $args)
    {
        return [new SelectableItemsFeature(), new PagingFeature()];
    }

    /**
     * @copydoc GridHandler::getSelectName()
     */
    public function getSelectName()
    {
        return 'selectedUsers';
    }

    //
    // Implemented methods from GridHandler.
    //
    /**
     * @copydoc GridHandler::isDataElementSelected()
     */
    public function isDataElementSelected($gridDataElement)
    {
        return false; // Nothing is selected by default
    }

    /**
     * @copydoc GridHandler::loadData()
     *
     * @param PKPRequest $request
     *
     * @return array Grid data.
     */
    protected function loadData($request, $filter)
    {
        // Get the context.
        $context = $request->getContext();

        // Get all users for this context that match search criteria.
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $rangeInfo = $this->getGridRangeInfo($request, $this->getId());

        return $users = $userGroupDao->getUsersById(
            $filter['userGroup'],
            $context->getId(),
            $filter['searchField'],
            $filter['search'] ? $filter['search'] : null,
            $filter['searchMatch'],
            $rangeInfo
        );
    }

    /**
     * @copydoc GridHandler::renderFilter()
     */
    public function renderFilter($request, $filterData = [])
    {
        $context = $request->getContext();
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $userGroups = $userGroupDao->getByContextId($context->getId());
        $userGroupOptions = ['' => __('grid.user.allRoles')];
        while ($userGroup = $userGroups->next()) {
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
            'matchOptions' => $matchOptions
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
        $userGroup = $request->getUserVar('userGroup') ? (int)$request->getUserVar('userGroup') : null;
        $searchField = $request->getUserVar('searchField');
        $searchMatch = $request->getUserVar('searchMatch');
        $search = $request->getUserVar('search');

        return $filterSelectionData = [
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
        return 'controllers/grid/users/exportableUsers/userGridFilter.tpl';
    }

    /**
     * @see GridHandler::getRequestArgs()
     */
    public function getRequestArgs()
    {
        return array_merge(parent::getRequestArgs(), ['pluginName' => $this->_getPluginName()]);
    }

    /**
     * Fetch the name of the plugin for this grid's calling context.
     *
     * @return string
     */
    public function _getPluginName()
    {
        return $this->_pluginName;
    }
}
