<?php

/**
 * @file classes/controllers/grid/feature/OrderListbuilderItemsFeature.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OrderListbuilderItemsFeature
 * @ingroup controllers_grid_feature
 *
 * @brief Implements listbuilder ordering functionality.
 *
 */

import('lib.pkp.classes.controllers.grid.feature.OrderItemsFeature');

class OrderListbuilderItemsFeature extends OrderItemsFeature {

	/**
	 * Constructor.
	 */
	function __construct() {
		parent::__construct(false);
	}


	//
	// Extended methods from GridFeature.
	//
	/**
	 * @see GridFeature::getJSClass()
	 */
	function getJSClass() {
		return '$.pkp.classes.features.OrderListbuilderItemsFeature';
	}
}
