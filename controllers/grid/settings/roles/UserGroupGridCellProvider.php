<?php

/**
 * @file controllers/grid/settings/roles/UserGroupGridCellProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserGroupGridCellProvider
 *
 * @ingroup controllers_grid_settings_roles
 *
 * @brief Cell provider for columns in a user group grid.
 */

namespace PKP\controllers\grid\settings\roles;

use APP\core\Application;
use APP\facades\Repo;
use PKP\controllers\grid\GridCellProvider;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\db\DAORegistry;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxAction;

class UserGroupGridCellProvider extends GridCellProvider
{
    /**
     * Extracts variables for a given column from a data element
     * so that they may be assigned to template before rendering.
     *
     * @param \PKP\controllers\grid\GridRow $row
     * @param GridColumn $column
     *
     * @return array
     */
    public function getTemplateVarsFromRowColumn($row, $column)
    {
        $userGroup = $row->getData(); /** @var UserGroup $userGroup */
        $columnId = $column->getId();
        $workflowStages = Application::getApplicationStages();
        $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */

        $assignedStages = Repo::userGroup()->getAssignedStagesByUserGroupId($userGroup->getContextId(), $userGroup->getId())->toArray();

        switch ($columnId) {
            case 'name':
                return ['label' => $userGroup->getLocalizedName()];
            case 'roleId':
                $roleNames = Application::getRoleNames(false, [$userGroup->getRoleId()]);
                return ['label' => __(array_shift($roleNames))];
            case in_array($columnId, $workflowStages):
                // Set the state of the select element that will
                // be used to assign the stage to the user group.
                $selectDisabled = false;
                if (in_array($columnId, $roleDao->getForbiddenStages($userGroup->getRoleId()))) {
                    // This stage should not be assigned to the user group.
                    $selectDisabled = true;
                }

                return ['selected' => in_array($columnId, $assignedStages),
                    'disabled' => $selectDisabled];
            default:
                break;
        }

        return parent::getTemplateVarsFromRowColumn($row, $column);
    }

    /**
     * @copydoc GridCellProvider::getCellActions()
     */
    public function getCellActions($request, $row, $column, $position = GridHandler::GRID_ACTION_POSITION_DEFAULT)
    {
        $workflowStages = Application::getApplicationStages();
        $columnId = $column->getId();

        if (in_array($columnId, $workflowStages)) {
            $userGroup = $row->getData(); /** @var UserGroup $userGroup */

            $assignedStages = Repo::userGroup()->getAssignedStagesByUserGroupId($userGroup->getContextId(), $userGroup->getId())->toArray();

            $router = $request->getRouter();
            $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */

            if (!in_array($columnId, $roleDao->getForbiddenStages($userGroup->getRoleId()))) {
                if (in_array($columnId, $assignedStages)) {
                    $operation = 'unassignStage';
                    $actionTitleKey = 'grid.userGroup.unassignStage';
                } else {
                    $operation = 'assignStage';
                    $actionTitleKey = 'grid.userGroup.assignStage';
                }
                $actionArgs = array_merge(
                    ['stageId' => $columnId],
                    $row->getRequestArgs()
                );

                $actionUrl = $router->url($request, null, null, $operation, null, $actionArgs);
                $actionRequest = new AjaxAction($actionUrl);

                $linkAction = new LinkAction(
                    $operation,
                    $actionRequest,
                    __($actionTitleKey),
                    null
                );

                return [$linkAction];
            }
        }

        return parent::getCellActions($request, $row, $column, $position);
    }
}
