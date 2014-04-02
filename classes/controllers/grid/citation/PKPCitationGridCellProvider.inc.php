<?php

/**
 * @file classes/controllers/grid/citation/PKPCitationGridCellProvider.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPCitationGridCellProvider
 * @ingroup controllers_grid_citation
 *
 * @brief Grid cell provider for the citation editor grid.
 */

import('lib.pkp.classes.controllers.grid.DataObjectGridCellProvider');

class PKPCitationGridCellProvider extends DataObjectGridCellProvider {
	/**
	 * Constructor
	 */
	function PKPCitationGridCellProvider() {
		parent::DataObjectGridCellProvider();
	}

	//
	// Template methods from GridCellProvider
	//
	/**
	 * @see GridCellProvider::getTemplateVarsFromRowColumn()
	 */
	function getTemplateVarsFromRowColumn(&$row, $column) {
		$templateVars = parent::getTemplateVarsFromRowColumn($row, $column);
		$element =& $row->getData();
		assert(is_a($element, 'Citation'));
		$templateVars['isApproved'] = ($element->getCitationState() == CITATION_APPROVED ? true : false);
		$templateVars['isCurrentItem'] = $row->getIsCurrentItem();
		$templateVars['citationSeq'] = $element->getSeq();
		return $templateVars;
	}


	/**
	 * @see GridCellProvider::getCellActions()
	 */
	function &getCellActions(&$request, &$row, &$column, $position = GRID_ACTION_POSITION_DEFAULT) {
		// The citation grid retrieves actions from the row.
		$actions =& $row->getCellActions($request, $column, $position);
		return $actions;
	}
}

?>
