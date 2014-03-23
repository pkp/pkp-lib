<?php
/**
 * @file controllers/listbuilder/settings/reviewForms/ReviewFormElementResponseItemListbuilderGridCellProvider.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReviewFormElementResponseItemListbuilderGridCellProvider
 * @ingroup controllers_listbuilder_settings_reviewForms
 *
 * @brief Review form element response item listbuilder grid handler.
 */

import('lib.pkp.classes.controllers.grid.GridCellProvider');

class ReviewFormElementResponseItemListbuilderGridCellProvider extends GridCellProvider {
	/**
	 * Constructor
	 */
	function ReviewFormElementResponseItemListbuilderGridCellProvider () {
		parent::GridCellProvider();
	}

	//
	// Template methods from GridCellProvider
	//
	/**
	 * @see GridCellProvider::getTemplateVarsFromRowColumn()
	 */
	function getTemplateVarsFromRowColumn($row, $column) {
		$possibleResponse = $row->getData();
		$order = (int)$possibleResponse['order'];
		$content = $possibleResponse['content'];

		$columnId = $column->getId();
		assert(is_int($order) && !empty($columnId)); 

		switch ($columnId) {
			case 'possibleResponse':
				return array('labelKey' => $order, 'label' => $content);
		}
	}
}

?>
