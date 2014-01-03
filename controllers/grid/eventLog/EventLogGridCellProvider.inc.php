<?php

/**
 * @file controllers/grid/eventLog/EventLogGridCellProvider.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EventLogGridCellProvider
 * @ingroup controllers_grid_catalogEntry
 *
 * @brief Cell provider for event log entries.
 */

import('lib.pkp.classes.controllers.grid.DataObjectGridCellProvider');

class EventLogGridCellProvider extends DataObjectGridCellProvider {
	/**
	 * Constructor
	 */
	function EventLogGridCellProvider() {
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
			case 'date':
				return array('label' => $element->getDateLogged());
			case 'event':
				return array('label' => $element->getTranslatedMessage());
			case 'user':
				return array('label' => $element->getUserFullname());
			default:
				assert(false);
		}
	}
}

?>
