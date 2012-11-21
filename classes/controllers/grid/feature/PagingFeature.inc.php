<?php

/**
 * @file classes/controllers/grid/feature/PagingFeature.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PagingFeature
 * @ingroup controllers_grid_feature
 *
 * @brief Add paging functionality to grids.
 *
 */

import('lib.pkp.classes.controllers.grid.feature.GridFeature');

class PagingFeature extends GridFeature{

	/** @var ItemIterator */
	private $_itemIterator;

	/**
	 * Constructor.
	 */
	function PagingFeature() {
		parent::GridFeature('paging');
	}

	//
	// Getters and setters.
	//
	/**
	 * Get item iterator.
	 * @return ItemIterator
	 */
	function getItemIterator() {
		return $this->_itemIterator;
	}


	//
	// Extended methods from GridFeature.
	//
	/**
	 * @see GridFeature::getJSClass()
	 */
	function getJSClass() {
		return '$.pkp.classes.features.PagingFeature';
	}

	/**
	 * @see GridFeature::setOptions()
	 */
	function setOptions($request, $grid) {
		// Get the default items per page setting value.
		$rangeInfo = PKPHandler::getRangeInfo($request, $grid->getId());
		$defaultItemsPerPage = $rangeInfo->getCount();

		// Check for a component level items per page setting.
		$componentItemsPerPage = $request->getUserVar($this->_getItemsPerPageParamName($grid->getId()));
		if ($componentItemsPerPage) {
			$currentItemsPerPage = $componentItemsPerPage;
		} else {
			$currentItemsPerPage = $defaultItemsPerPage;
		}

		$iterator = $this->getItemIterator();

		$options = array(
			'itemsPerPageParamName' => $this->_getItemsPerPageParamName($grid->getId()),
			'defaultItemsPerPage' => $defaultItemsPerPage,
			'currentItemsPerPage' => $currentItemsPerPage,
			'itemsTotal' => $iterator->getCount(),
			'pageParamName' => PKPHandler::getPageParamName($grid->getId()),
			'currentPage' => $iterator->getPage()
		);

		$this->addOptions($options);

		parent::setOptions($request, $grid);
	}

	/**
	 * @see GridFeature::fetchUIElements()
	 */
	function fetchUIElements($request, $grid) {
		$iterator = $this->getItemIterator();
		$options = $this->getOptions();

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('iterator', $iterator);
		$templateMgr->assign('currentItemsPerPage', $options['currentItemsPerPage']);

		return array('pagingMarkup' => $templateMgr->fetch('controllers/grid/feature/gridPaging.tpl'));
	}


	//
	// Hooks implementation.
	//
	/**
	 * @see GridFeature::setGridDataElements()
	 */
	function setGridDataElements($args) {
		$grid = $args['grid'];
		$data =& $args['data'];

		if (is_array($data)) {
			import('lib.pkp.classes.core.ArrayItemIterator');
			$request = Application::getRequest();
			$rangeInfo = $grid->getGridRangeInfo($request, $grid->getId());
			$itemIterator = new ArrayItemIterator($data, $rangeInfo->getPage(), $rangeInfo->getCount());
			$this->_itemIterator = $itemIterator;
			$data = $itemIterator->toArray();
		} elseif (is_a($data, 'ItemIterator')) {
			$this->_itemIterator = $data;
		}
	}

	/**
	 * @see GridFeature::getRequestArgs()
	 */
	function getRequestArgs($args) {
		$grid = $args['grid'];
		$requestArgs =& $args['requestArgs'];

		// Add paging info so grid actions will not loose paging context.
		// Only works if grid link actions use the getRequestArgs
		// returned content.
		$request = Application::getRequest();
		$rangeInfo = $grid->getGridRangeInfo($request, $grid->getId());
		$requestArgs[GridHandler::getPageParamName($grid->getId())] = $rangeInfo->getPage();
		$requestArgs[$this->_getItemsPerPageParamName($grid->getId())] = $rangeInfo->getCount();
	}

	/**
	 * @see GridFeature::getGridRangeInfo()
	 */
	function getGridRangeInfo($args) {
		$request = $args['request'];
		$grid = $args['grid'];
		$rangeInfo = $args['rangeInfo'];

		// Add grid level items per page setting, if any.
		$itemsPerPage = $request->getUserVar($this->_getItemsPerPageParamName($grid->getId()));
		if ($itemsPerPage) {
			$rangeInfo->setCount($itemsPerPage);
		}
	}


	//
	// Private helper methods.
	//
	/**
	 * Get the range info items per page parameter name.
	 * @param $rangeName string
	 * @return string
	 */
	private function _getItemsPerPageParamName($rangeName) {
		return $rangeName . 'ItemsPerPage';
	}
}

?>
