<?php

/**
 * @file controllers/grid/eventLog/EventLogGridCellProvider.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EventLogGridCellProvider
 * @ingroup controllers_grid_catalogEntry
 *
 * @brief Cell provider for event log entries.
 */

import('lib.pkp.classes.controllers.grid.DataObjectGridCellProvider');

class EventLogGridCellProvider extends DataObjectGridCellProvider {
	//
	// Template methods from GridCellProvider
	//
	/**
	 * @copydoc GridCellProvider::getTemplateVarsFromRowColumn()
	 */
	function getTemplateVarsFromRowColumn($row, $column) {
		$element = $row->getData();
		$columnId = $column->getId();
		assert(is_a($element, 'DataObject') && !empty($columnId));
		switch ($columnId) {
			case 'date':
				return array('label' => is_a($element, 'EventLogEntry') ? $element->getDateLogged() : $element->getDateSent());
			case 'event':
				return array('label' => is_a($element, 'EventLogEntry') ? $element->getTranslatedMessage() : $element->getPrefixedSubject());
			case 'user':
				return array('label' => is_a($element, 'EventLogEntry') ? $element->getUserFullName() : $element->getSenderFullName());
			default:
				assert(false);
		}
	}
}

?>
