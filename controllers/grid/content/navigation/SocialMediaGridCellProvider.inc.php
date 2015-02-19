<?php

/**
 * @file controllers/grid/content/navigation/SocialMediaGridCellProvider.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SocialMediaGridCellProvider
 * @ingroup controllers_grid_content_navigation
 *
 * @brief class for a cell provider that can retrieve labels for social media objects
 */

import('lib.pkp.classes.controllers.grid.DataObjectGridCellProvider');

class SocialMediaGridCellProvider extends DataObjectGridCellProvider {
	/**
	 * Constructor
	 */
	function SocialMediaGridCellProvider() {
		parent::DataObjectGridCellProvider();
	}

	//
	// Template methods from GridCellProvider
	//
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
		assert(is_a($element, 'DataObject') && !empty($columnId));
		switch ($columnId) {
			case 'platform':
				return array('label' => $element->getLocalizedPlatform());
			case 'inCatalog':
				return array('isChecked' => $element->getIncludeInCatalog());
		}
	}
}
?>
