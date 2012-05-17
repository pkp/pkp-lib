<?php

/**
 * @file classes/controllers/grid/feature/GridCategoryAccordionFeature.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GridCategoryAccordionFeature
 * @ingroup controllers_grid_feature
 *
 * @brief Transform default grid categories in accordions.
 *
 */

import('lib.pkp.classes.controllers.grid.feature.GridFeature');
import('lib.pkp.classes.linkAction.request.NullAction');

class GridCategoryAccordionFeature extends GridFeature{

	/**
	 * Constructor.
	 */
	function GridCategoryAccordionFeature() {
		parent::GridFeature('categoryAccordion');
	}

	/**
	 * @see GridFeature::getJSClass()
	 */
	function getJSClass() {
		return '$.pkp.classes.features.GridCategoryAccordionFeature';
	}


	//
	// Hooks implementation.
	//
	/**
	 * @see GridFeature::gridInitialize()
	 */
	function gridInitialize($args) {
		$grid =& $args['grid'];

		$grid->addAction(
			new LinkAction(
				'expandAll',
				new NullAction(),
				__('grid.action.extendAll'),
				'expand_all'
			)
		);

		$grid->addAction(
			new LinkAction(
				'collapseAll',
				new NullAction(),
				__('grid.action.collapseAll'),
				'collapse_all'
			)
		);
	}

	/**
	 * @see GridFeature::getInitializedCategoryRowInstance()
	 */
	function getInitializedCategoryRowInstance($args) {
		$row =& $args['row'];

		$row->addAction(
			new LinkAction(
				'expand',
				new NullAction(),
				'',
				'expanded'
			), GRID_ACTION_POSITION_DEFAULT
		);

		$row->addAction(
			new LinkAction(
				'collapse',
				new NullAction(),
				'',
				'collapsed'
			), GRID_ACTION_POSITION_DEFAULT
		);
	}
}

?>
