<?php

/**
 * @file controllers/grid/users/author/PKPAuthorGridCellProvider.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class DataObjectGridCellProvider
 * @ingroup controllers_grid_users_author
 *
 * @brief Base class for a cell provider that can retrieve labels for submission contributors
 */

import('lib.pkp.classes.controllers.grid.DataObjectGridCellProvider');

class PKPAuthorGridCellProvider extends DataObjectGridCellProvider {
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
	}

	//
	// Template methods from GridCellProvider
	//
	/**
	 * @copydoc GridCellProvider::getTemplateVarsFromRowColumn()
	 */
	function getTemplateVarsFromRowColumn($request, $row, $column) {
		$element = $row->getData();
		$columnId = $column->getId();
		assert(is_a($element, 'DataObject') && !empty($columnId));
		switch ($columnId) {
			case 'name':
				return array('label' => $element->getFullName());
			case 'role':
				return array('label' => $element->getLocalizedUserGroupName());
			case 'email':
				return parent::getTemplateVarsFromRowColumn($request, $row, $column);
			case 'principalContact':
				return array('isPrincipalContact' => $element->getPrimaryContact());
			case 'includeInBrowse':
				return array('includeInBrowse' => $element->getIncludeInBrowse());
		}
	}
}

?>
