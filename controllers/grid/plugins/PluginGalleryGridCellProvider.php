<?php
/**
 * @file controllers/grid/plugins/PluginGalleryGridCellProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PluginGalleryGridCellProvider
 *
 * @ingroup controllers_grid_plugins
 *
 * @brief Provide information about plugins to the plugin gallery grid handler
 */

namespace PKP\controllers\grid\plugins;

use PKP\controllers\grid\GridCellProvider;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\plugins\GalleryPlugin;

class PluginGalleryGridCellProvider extends GridCellProvider
{
    /**
     * Extracts variables for a given column from a data element
     * so that they may be assigned to template before rendering.
     *
     * @param \PKP\controllers\grid\GridRow $row
     * @param GridColumn $column
     *
     * @return ?array
     */
    public function getTemplateVarsFromRowColumn($row, $column)
    {
        $element = $row->getData();
        $columnId = $column->getId();
        assert(($element instanceof GalleryPlugin) && !empty($columnId));
        switch ($columnId) {
            case 'name':
                // The name is returned as an action.
                return ['label' => ''];
            case 'summary':
                return ['label' => $element->getLocalizedSummary()];
            case 'status':
                return ['label' => __(match($element->getCurrentStatus()) {
                    GalleryPlugin::PLUGIN_GALLERY_STATE_NEWER => 'manager.plugins.installedVersionNewer.short',
                    GalleryPlugin::PLUGIN_GALLERY_STATE_UPGRADABLE => 'manager.plugins.installedVersionOlder.short',
                    GalleryPlugin::PLUGIN_GALLERY_STATE_CURRENT => 'manager.plugins.installedVersionNewest.short',
                    GalleryPlugin::PLUGIN_GALLERY_STATE_AVAILABLE => null,
                    GalleryPlugin::PLUGIN_GALLERY_STATE_INCOMPATIBLE => 'manager.plugins.noCompatibleVersion.short',
                })];
        }
    }

    /**
     * Get cell actions associated with this row/column combination
     *
     * @param \PKP\controllers\grid\GridRow $row
     * @param GridColumn $column
     *
     * @return array an array of LinkAction instances
     */
    public function getCellActions($request, $row, $column, $position = GridHandler::GRID_ACTION_POSITION_DEFAULT)
    {
        $element = $row->getData();
        switch ($column->getId()) {
            case 'name':
                $router = $request->getRouter();
                return [new LinkAction(
                    'moreInformation',
                    new AjaxModal(
                        $router->url($request, null, null, 'viewPlugin', null, ['rowId' => $row->getId() + 1]),
                        htmlspecialchars($element->getLocalizedName()),
                        'modal_information',
                        true
                    ),
                    htmlspecialchars($element->getLocalizedName()),
                    'details'
                )];
        }
        return parent::getCellActions($request, $row, $column, $position);
    }
}
