<?php

/**
 * @file classes/controllers/grid/feature/OrderGridItemsFeature.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OrderGridItemsFeature
 * @ingroup controllers_grid_feature
 *
 * @brief Implements grid ordering functionality.
 *
 */

import('lib.pkp.classes.controllers.grid.feature.OrderItemsFeature');

class OrderGridItemsFeature extends OrderItemsFeature{

	/**
	 * Constructor.
	 * @param $overrideRowTemplate boolean This feature uses row
	 * actions and it will force the usage of the gridRow.tpl.
	 * If you want to use a different grid row template file, set this flag to
	 * false and make sure to use a template file that adds row actions.
	 */
	function OrderGridItemsFeature($overrideRowTemplate = true) {
		parent::OrderItemsFeature($overrideRowTemplate);
	}


	//
	// Extended methods from GridFeature.
	//
	/**
	 * @see GridFeature::getJSClass()
	 */
	function getJSClass() {
		return '$.pkp.classes.features.OrderGridItemsFeature';
	}


	//
	// Hooks implementation.
	//
	/**
	 * @see GridFeature::saveSequence()
	 * @param $args array
	 */
	function saveSequence($args) {
		$request =& $args['request'];
		$grid =& $args['grid'];

		$data = json_decode($request->getUserVar('data'));

		$gridElements = $grid->getGridDataElements($request);
		$firstSeqValue = $grid->getDataElementSequence(reset($gridElements));
		foreach ($gridElements as $rowId => $element) {
			$rowPosition = array_search($rowId, $data);
			$newSequence = $firstSeqValue + $rowPosition;
			$currentSequence = $grid->getDataElementSequence($element);
			if ($newSequence != $currentSequence) {
				$grid->setDataElementSequence($request, $rowId, $element, $newSequence);
			}
		}
	}
}

?>
