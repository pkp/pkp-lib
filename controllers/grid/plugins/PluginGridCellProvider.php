<?php

/**
 * @file controllers/grid/plugins/PluginGridCellProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PluginGridCellProvider
 *
 * @ingroup controllers_grid_plugins
 *
 * @brief Cell provider for columns in a plugin grid.
 */

namespace PKP\controllers\grid\plugins;

use PKP\controllers\grid\GridCellProvider;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxAction;
use PKP\linkAction\request\RemoteActionConfirmationModal;
use PKP\plugins\Plugin;

class PluginGridCellProvider extends GridCellProvider
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
        $plugin = & $row->getData();
        $columnId = $column->getId();
        assert(is_a($plugin, 'Plugin') && !empty($columnId));

        switch ($columnId) {
            case 'name':
                return ['label' => $plugin->getDisplayName()];
                break;
            case 'category':
                return ['label' => $plugin->getCategory()];
                break;
            case 'description':
                return ['label' => $plugin->getDescription()];
                break;
            case 'enabled':
                $isEnabled = $plugin->getEnabled();
                return [
                    'selected' => $isEnabled,
                    'disabled' => $isEnabled ? !$plugin->getCanDisable() : !$plugin->getCanEnable(),
                ];
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
        switch ($column->getId()) {
            case 'enabled':
                $plugin = $row->getData(); /** @var Plugin $plugin */
                $requestArgs = array_merge(
                    ['plugin' => $plugin->getName()],
                    $row->getRequestArgs()
                );
                switch (true) {
                    case $plugin->getEnabled() && $plugin->getCanDisable():
                        // Create an action to disable the plugin
                        return [new LinkAction(
                            'disable',
                            new RemoteActionConfirmationModal(
                                $request->getSession(),
                                __('grid.plugin.disable'),
                                __('common.disable'),
                                $request->url(null, null, 'disable', null, $requestArgs)
                            ),
                            __('manager.plugins.disable'),
                            null
                        )];
                        break;
                    case !$plugin->getEnabled() && $plugin->getCanEnable():
                        // Create an action to enable the plugin
                        return [new LinkAction(
                            'enable',
                            new AjaxAction(
                                $request->url(null, null, 'enable', null, array_merge(
                                    ['csrfToken' => $request->getSession()->getCSRFToken()],
                                    $requestArgs
                                ))
                            ),
                            __('manager.plugins.enable'),
                            null
                        )];
                        break;
                }
        }
        return parent::getCellActions($request, $row, $column, $position);
    }
}
