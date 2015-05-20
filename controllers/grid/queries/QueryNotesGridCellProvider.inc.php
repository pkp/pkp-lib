<?php

/**
 * @file controllers/grid/queries/QueryNotesGridCellProvider.inc.php
 *
 * Copyright (c) 2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class QueryNotesGridCellProvider
 * @ingroup controllers_grid_queries
 *
 * @brief Base class for a cell provider that can retrieve query note info.
 */

import('lib.pkp.classes.controllers.grid.DataObjectGridCellProvider');

class QueryNotesGridCellProvider extends DataObjectGridCellProvider {
	/**
	 * Constructor
	 */
	function QueryNotesGridCellProvider() {
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
		$user = $element->getUser();

		switch ($columnId) {
			case 'from':
				return array('label' => $user->getUsername() . '<br />' . date('M/d', strtotime($element->getDateCreated())));
		}

		return parent::getTemplateVarsFromRowColumn($row, $column);
	}
}

?>
