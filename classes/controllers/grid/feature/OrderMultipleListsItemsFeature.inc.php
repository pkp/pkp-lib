<?php

/**
 * @file classes/controllers/grid/feature/OrderMultipleListsItemsFeature.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OrderMultipleListsItemsFeature
 * @ingroup controllers_grid_feature
 *
 * @brief Implements multiple lists listbuilder ordering functionality.
 *
 */

import('lib.pkp.classes.controllers.grid.feature.OrderItemsFeature');

class OrderMultipleListsItemsFeature extends OrderItemsFeature {

	/**
	 * Constructor.
	 */
	function OrderMultipleListsItemsFeature() {
		parent::OrderItemsFeature(false);
	}


	//
	// Extended methods from GridFeature.
	//
	/**
	 * @see GridFeature::getJSClass()
	 */
	function getJSClass() {
		return '$.pkp.classes.features.OrderMultipleListsItemsFeature';
	}


	//
	// Extended methods from OrderItemsFeature.
	//
	/**
	 * @see OrderItemsFeature::isOrderActionNecessary()
	 */
	function isOrderActionNecessary() {
		// The component that this feature is attached will always
		// stay in ordering mode for now.
		return false;
	}
}

?>
