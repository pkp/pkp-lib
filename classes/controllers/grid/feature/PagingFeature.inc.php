<?php

/**
 * @file classes/controllers/grid/feature/PagingFeature.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
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
	 * @param $request PKPRequest
	 * @param $grid Grid
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
	 * @param $request PKPRequest
	 * @param $grid Grid
	 */
	function fetchUIElements($request, $grid) {
		$iterator = $this->getItemIterator();
		$options = $this->getOptions();

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('iterator', $iterator);
		$templateMgr->assign('currentItemsPerPage', $options['currentItemsPerPage']);
		$templateMgr->assign('grid', $grid);

		return array('pagingMarkup' => $templateMgr->fetch('controllers/grid/feature/gridPaging.tpl'));
	}


	//
	// Hooks implementation.
	//
	/*
	 * @see GridFeature::gridInitialize()
	 * The feature will know about the current filter
	 * value so it can request grid refreshes keeping
	 * the filter.
	 * @param $args array
	 */
	function getGridDataElements($args) {
		$filter = $args['filter'];

		if (is_array($filter) && !empty($filter)) {
			$this->addOptions(array('filter' => json_encode($filter)));
		}
	}


	/**
	 * @see GridFeature::setGridDataElements()
	 * @param $args array
	 */
	function setGridDataElements($args) {
		$grid =& $args['grid'];
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
	 * @param $args array
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
	 * @param $args array
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

	/**
	 * @see GridFeature::fetchRow()
	 * Check if user really deleted a row. Handle following cases:
	 * 1 - recently added requested row is on previous pages and its
	 * addition changes the current requested page items;
	 * 2 - deleted a row from a page that's not the last one;
	 * 3 - deleted the last row from a page that's not the last one;
	 *
	 * The solution is:
	 * 1 - fetch the first grid data row;
	 * 2 - fetch the last grid data row;
	 * 3 - send a request to refresh the entire grid usign the previous
	 * page.
	 * @param $args array
	 */
	function fetchRow($args) {
		$request = $args['request'];
		$grid = $args['grid'];
		$row = $args['row'];
		$jsonMessage = $args['jsonMessage'];
		$pagingAttributes = array();

		if (is_null($row)) {
			$gridData = $grid->getGridDataElements($request);
			$iterator = $this->getItemIterator();
			$rangeInfo = $grid->getGridRangeInfo($request, $grid->getId());

			// Check if row was really deleted or if the requested row is
			// just not inside the requested range.
			$deleted = true;
			$topLimitRowId = (int) $request->getUserVar('topLimitRowId');
			$bottomLimitRowId = (int) $request->getUserVar('bottomLimitRowId');

			reset($gridData);
			$firstDataId = key($gridData);
			next($gridData);
			$secondDataId = key($gridData);
			end($gridData);
			$lastDataId = key($gridData);

			if ($secondDataId == $topLimitRowId) {
				$deleted = false;
				// Case 1.
				// Row was added but it's on previous pages, so the first
				// item of the grid was moved to the second place by the added
				// row. Render the first one that's currently not visible yet in
				// grid.
				$args = array('rowId' => $firstDataId);
				$row = $grid->getRequestedRow($request, $args);
				$pagingAttributes['newTopRow'] = $grid->renderRow($request, $row);
			}

			if ($firstDataId == $topLimitRowId && $lastDataId == $bottomLimitRowId) {
				$deleted = false;
			}

			if ($deleted) {
				if ((empty($gridData) ||
					// When DAOResultFactory, it seems that if no items were found for the current
					// range information, the last page is fetched, which give us grid data even if
					// the current page is empty. So we check for iterator and rangeInfo current pages.
					$iterator->getPage() != $rangeInfo->getPage())
					&& $iterator->getPageCount() >= 1) {
					// Case 3.
					$pagingAttributes['loadLastPage'] = true;
				} else {
					if (count($gridData) >= $rangeInfo->getCount()) {
						// Case 2.
						// Get the last data element id of the current page.
						end($gridData);
						$firstRowId = key($gridData);

						// Get the row and render it.
						$args = array('rowId' => $firstRowId);
						$row = $grid->getRequestedRow($request, $args);
						$pagingAttributes['deletedRowReplacement'] = $grid->renderRow($request, $row);
					}
				}
			}
		}

		// Render the paging options, including updated markup.
		$this->setOptions($request, $grid);
		$pagingAttributes['pagingInfo'] = $this->getOptions();

		// Add paging attributes to json so grid can update UI.
		$additionalAttributes = $jsonMessage->getAdditionalAttributes();
		$jsonMessage->setAdditionalAttributes(array_merge(
			$pagingAttributes,
			$additionalAttributes)
		);
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
