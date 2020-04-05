<?php
/**
 * @file controllers/listbuilder/settings/reviewForms/ReviewFormElementResponseItemListbuilderGridCellProvider.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewFormElementResponseItemListbuilderGridCellProvider
 * @ingroup controllers_listbuilder_settings_reviewForms
 *
 * @brief Review form element response item listbuilder grid handler.
 */

import('lib.pkp.classes.controllers.grid.GridCellProvider');

class ReviewFormElementResponseItemListbuilderGridCellProvider extends GridCellProvider {

	//
	// Template methods from GridCellProvider
	//
	/**
	 * @see GridCellProvider::getTemplateVarsFromRowColumn()
	 */
	function getTemplateVarsFromRowColumn($row, $column) {
		switch ($column->getId()) {
			case 'possibleResponse':
				$possibleResponse = $row->getData();
				$contentColumn = $possibleResponse[0];
				$content = $contentColumn['content'];
				return array('label' => $content);
		}
		assert(false);
	}
}


