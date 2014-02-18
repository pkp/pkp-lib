<?php

/**
 * @file controllers/listbuilder/settings/BlockPluginsListbuilderGridCellProvider.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class BlockPluginsListbuilderGridCellProvider
 * @ingroup controllers_listbuilder_settings
 *
 * @brief Block plugins listbuilder cell provider.
 */

import('lib.pkp.classes.controllers.grid.GridCellProvider');

class BlockPluginsListbuilderGridCellProvider extends GridCellProvider {
	/**
	 * Constructor
	 */
	function BlockPluginsListbuilderGridCellProvider() {
		parent::GridCellProvider();
	}

	//
	// Template methods from GridCellProvider
	//
	/**
	 * This implementation assumes a simple data element array that
	 * has column ids as keys.
	 * @see GridCellProvider::getTemplateVarsFromRowColumn()
	 * @param $row GridRow
	 * @param $column GridColumn
	 * @return array
	 */
	function getTemplateVarsFromRowColumn($row, $column) {
		$plugin =& $row->getData();
		$columnId = $column->getId();
		assert((is_a($plugin, 'Plugin')) && !empty($columnId));

		return array('label' => $plugin->getDisplayName());
	}
}

?>
