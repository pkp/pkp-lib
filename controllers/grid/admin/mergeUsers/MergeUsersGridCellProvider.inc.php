<?php

/**
 * @file controllers/grid/admin/mergeUsers/MergeUsersGridCellProvider.inc.php
 *
 * Copyright (c) 2003-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MergeUsersGridCellProvider
 * @ingroup controllers_grid_admin_mergeUsers
 *
 * @brief Subclass for the merge user grid column's cell provider
 */

import('lib.pkp.classes.controllers.grid.GridCellProvider');

class MergeUsersGridCellProvider extends GridCellProvider {
	/**
	 * Constructor
	 */
	function MergeUsersGridCellProvider() {
		parent::GridCellProvider();
	}

	/**
	 * Extracts variables for a given column from a data element
	 * so that they may be assigned to template before rendering.
	 * @param $row GridRow
	 * @param $column GridColumn
	 * @return array
	 */
	function getTemplateVarsFromRowColumn(&$row, $column) {
		$element =& $row->getData();
		$columnId = $column->getId();
		assert(is_a($element, 'DataObject') && !empty($columnId));
		switch ($columnId) {
			case 'username':
				return array('label' => $element->getUsername());
				break;
			case 'name':
				return array('label' => $element->getFullName());
				break;
			case 'email':
				return array('label' => $element->getEmail());
				break;
			default:
				break;
		}
	}
}

?>
