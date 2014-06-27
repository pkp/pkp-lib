<?php
/**
 * @file controllers/grid/plugins/PluginGalleryGridCellProvider.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PluginGalleryGridCellProvider
 * @ingroup controllers_grid_plugins
 *
 * @brief Provide information about plugins to the plugin gallery grid handler
 */

import('lib.pkp.classes.controllers.grid.GridCellProvider');

class PluginGalleryGridCellProvider extends GridCellProvider {
	/**
	 * Constructor
	 */
	function PluginGalleryGridCellProvider() {
		parent::GridCellProvider();
	}

	/**
	 * Extracts variables for a given column from a data element
	 * so that they may be assigned to template before rendering.
	 * @param $row GridRow
	 * @param $column GridColumn
	 * @return array
	 */
	function getTemplateVarsFromRowColumn($row, $column) {
		$element = $row->getData();
		$columnId = $column->getId();
		assert(is_a($element, 'GalleryPlugin') && !empty($columnId));
		switch ($columnId) {
			case 'name':
				$label = $element->getLocalizedName();
				return array('label' => $label);
				break;
			case 'description':
				$label = $element->getLocalizedDescription();
				return array('label' => $label);
				break;
			default:
				break;
		}
	}
}

?>
