<?php

/**
 * @file classes/controllers/grid/feature/OrderItemsFeature.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OrderItemsFeature
 * @ingroup controllers_grid_feature
 *
 * @brief Base class for grid widgets ordering functionality.
 *
 */

import('lib.pkp.classes.controllers.grid.feature.GridFeature');

class OrderItemsFeature extends GridFeature{

	/** @var $customRowTemplate boolean */
	var $_overrideRowTemplate;

	/**
	 * Constructor.
	 */
	function OrderItemsFeature($overrideRowTemplate) {
		parent::GridFeature('orderItems');

		$this->setOverrideRowTemplate($overrideRowTemplate);
	}


	//
	// Getters and setters.
	//
	/**
	 * Set override row template flag.
	 * @param $customRowTemplate boolean
	 */
	function setOverrideRowTemplate($overrideRowTemplate) {
		$this->_overrideRowTemplate = $overrideRowTemplate;
	}

	/**
	 * Get override row template flag.
	 * @param $gridRow GridRow
	 * @return boolean
	 */
	function getOverrideRowTemplate(&$gridRow) {
		// Make sure we don't return the override row template
		// flag to objects that are not instances of GridRow class.
		if (get_class($gridRow) == 'GridRow') {
			return $this->_overrideRowTemplate;
		} else {
			return false;
		}
	}


	//
	// Extended methods from GridFeature.
	//
	/**
	 * @see GridFeature::setOptions()
	 */
	function setOptions(&$request, &$grid) {
		parent::setOptions($request, $grid);

		$router =& $request->getRouter();
		$this->addOptions(array(
			'saveItemsSequenceUrl' => $router->url($request, null, null, 'saveSequence', null, $grid->getRequestArgs())
		));
	}


	//
	// Hooks implementation.
	//
	/**
	 * @see GridFeature::getInitializedRowInstance()
	 */
	function getInitializedRowInstance($args) {
		$row =& $args['row'];
		$this->addRowOrderAction($row);
	}

	/**
	 * @see GridFeature::gridInitialize()
	 */
	function gridInitialize($args) {
		$grid =& $args['grid'];

		if ($this->isOrderActionNecessary()) {
			import('lib.pkp.classes.linkAction.request.NullAction');
			$grid->addAction(
				new LinkAction(
					'orderItems',
					new NullAction(),
					__('grid.action.order'),
					'order_items'
				)
			);
		}
	}

	/**
	 * @see GridFeature::fetchUIElements()
	 */
	function fetchUIElements(&$grid) {
		if ($this->isOrderActionNecessary()) {
			$templateMgr =& TemplateManager::getManager();
			$templateMgr->assign('gridId', $grid->getId());
			return array('orderFinishControls' => $templateMgr->fetch('controllers/grid/feature/gridOrderFinishControls.tpl'));
		}
	}


	//
	// Protected methods.
	//
	/**
	 * Add grid row order action.
	 * @param $row GridRow
	 * @param $actionPosition int
	 * @param $rowTemplate string
	 */
	function addRowOrderAction(&$row) {
		if ($this->getOverrideRowTemplate($row)) {
			$row->setTemplate('controllers/grid/gridRow.tpl');
		}

		import('lib.pkp.classes.linkAction.request.NullAction');
		$row->addAction(
			new LinkAction(
				'moveItem',
				new NullAction(),
				'',
				'order_items'
			), GRID_ACTION_POSITION_ROW_LEFT
		);
	}

	//
	// Protected template methods.
	//
	/**
	 * Return if this feature will use
	 * a grid level order action. Default is
	 * true, override it if needed.
	 * @return boolean
	 */
	function isOrderActionNecessary() {
		return true;
	}
}

?>
