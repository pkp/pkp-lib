<?php

/**
 * @file controllers/grid/settings/contributorRoles/ContributorRoleGridHandler.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ContributorRoleGridHandler
 *
 * @ingroup controllers_grid_settings_contributorRoles
 *
 * @brief Handle contributor role grid requests.
 */

namespace PKP\controllers\grid\settings\contributorRoles;

use APP\facades\Repo;
use PKP\author\contributorRole\ContributorRole;
use PKP\author\creditContributorRole\CreditContributorRole;
use PKP\controllers\grid\DataObjectGridCellProvider;
use PKP\controllers\grid\feature\OrderGridItemsFeature;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\controllers\grid\settings\contributorRoles\form\ContributorRoleForm;
use PKP\core\JSONMessage;
use PKP\core\PKPRequest;
use PKP\db\DAO;
use PKP\facades\Locale;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\linkAction\request\RemoteActionConfirmationModal;
use PKP\security\Role;
use PKP\security\authorization\CanAccessSettingsPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;

class ContributorRoleGridHandler extends GridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], [
            'fetchGrid', 'fetchRow',
            'addContributorRole', 'editContributorRole', 'updateContributorRole',
            'deleteContributorRole',
        ]);
    }

    //
    // Implement template methods from PKPHandler.
    //
    /**
     * @see PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);
        $this->addPolicy(new CanAccessSettingsPolicy());

        return parent::authorize($request, $args, $roleAssignments);
    }

    //
    // Overridden template methods
    //
    /**
     * Configure the grid
     *
     * @see GridHandler::initialize
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        // Set the grid title.
        $this->setTitle('grid.contributorRoles.title');

        // Add grid-level actions
        $router = $request->getRouter();
        $actionArgs = ['gridId' => $this->getId()];

        $this->addAction(
            new LinkAction(
                'addContributorRole',
                new AjaxModal(
                    $router->url($request, null, null, 'addContributorRole', null, $actionArgs),
                    __('grid.action.addContributorRole'),
                    null,
                    true
                ),
                __('grid.action.addContributorRole'),
                'add_item'
            )
        );

        // Columns
        $cellProvider = new DataObjectGridCellProvider();
        $cellProvider->setLocale(Locale::getLocale());
        $this->addColumn(
            new GridColumn(
                'name',
                'common.name',
                null,
                null,
                $cellProvider
            )
        );
    }

    /**
     * @copydoc GridHandler::loadData()
     */
    protected function loadData($request, $filter)
    {
        // Elements to be displayed in the grid
        return ContributorRole::query()->withContextId($request->getContext()->getId())->get()->all();
    }

    /**
     * @copydoc GridHandler::getRowInstance()
     *
     * @return ContributorRoleGridRow
     */
    protected function getRowInstance()
    {
        return new ContributorRoleGridRow();
    }

    /**
     * @copydoc GridHandler::getDataElementSequence()
     */
    public function getDataElementSequence($row)
    {
        return $row->id;
    }

    /**
     * @copydoc GridHandler::setDataElementSequence()
     */
    public function setDataElementSequence($request, $rowId, $gridDataElement, $newSequence) {}

    //
    // Public Genre Grid Actions
    //
    /**
     * An action to add a new contributor role
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function addContributorRole(array $args, PKPRequest $request): JSONMessage
    {
        // Calling editContributorRole with an empty row id will add a new contributor role.
        return $this->editContributorRole($args, $request);
    }

    /**
     * An action to edit a contributor role
     */
    public function editContributorRole(array $args, PKPRequest $request): JSONMessage
    {
        $roleId = isset($args['rowId']) ? (int) $args['rowId'] : null;

        $this->setupTemplate($request);

        $form = new ContributorRoleForm($roleId);

        $form->initData($args);

        return new JSONMessage(true, $form->fetch($request));
    }

    /**
     * Update a contributor role
     */
    public function updateContributorRole(array $args, PKPRequest $request): JSONMessage
    {
        $roleId = isset($args['rowId']) ? (int) $args['rowId'] : null;

        $form = new ContributorRoleForm($roleId);
        $form->readInputData();

        if ($form->validate()) {
            $form->execute();
            return DAO::getDataChangedEvent($roleId);
        }

        return new JSONMessage(false);
    }

    /**
     * Delete a contributor role.
     */
    public function deleteContributorRole(array $args, PKPRequest $request): JSONMessage
    {
        $roleId = isset($args['rowId']) ? (int) $args['rowId'] : null;
        $contextId = $request->getContext()->getId();

        if (!$request->checkCSRF()) {
            return new JSONMessage(false, __('form.csrfInvalid'));
        }

        if (!$roleId) {
            return new JSONMessage(false, __('manager.setup.errorDeletingItem'));
        }

        // At least one must exist
        if (ContributorRole::query()->withContextId($contextId)->count() < 2) {
            return new JSONMessage(false, __('manager.contributorRoles.alert.delete.atLeastOne'));
        }

        // Block the removal of role when in use
        if (CreditContributorRole::query()->withContributorRoleId($roleId)->count()) {
            return new JSONMessage(false, __('manager.contributorRoles.alert.delete.inUse'));
        }

        Repo::contributorRole()->delete(roleId: $roleId);

        return DAO::getDataChangedEvent($roleId);
    }
}
