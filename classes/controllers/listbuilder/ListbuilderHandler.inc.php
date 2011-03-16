<?php

/**
 * @file classes/controllers/listbuilder/ListbuilderHandler.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ListbuilderHandler
 * @ingroup controllers_listbuilder
 *
 * @brief Class defining basic operations for handling Listbuilder UI elements
 */

import('lib.pkp.classes.controllers.grid.GridHandler');
import('lib.pkp.classes.controllers.listbuilder.ListbuilderGridRow');

define('LISTBUILDER_SOURCE_TYPE_TEXT', 0);
define('LISTBUILDER_SOURCE_TYPE_SELECT', 1);
define('LISTBUILDER_SOURCE_TYPE_BOUND', 2);

// FIXME: Rather than inheriting from grid handler, common base
// functionality might better be factored into a common base handler
// class and then both, GridHandler and ListbuilderHandler should
// inherit from the common base class. The shared concept of grids
// and list builders is that both seem to work with element lists. Maybe
// ElementListHandler would be a good name then for a common base
// class? I'm not a 100% sure about this but it'll become obvious
// once you try. If there's considerable amounts of code in both
// the base class and the re-factored grid handler then you know
// you're on the right track.
class ListbuilderHandler extends GridHandler {

	/** @var string The label associated with the primary source to be added to the list **/
	var $_sourceTitle;

	/** @var integer Definition of the type of source **/
	var $_sourceType;

	/** @var array The current collection of items in the list **/
	var $_items;

	/** @var string The title of the item collection **/
	var $_listTitle;

	/** @var array Array of optional attributes **/
	var $_attributeNames;

	/** @var array Array of optional data **/
	var $_additionalData;

	/** @var array Array of strings containing possible items that are stored in the source list */
	var $_possibleItems = array();


	/**
	 * Constructor.
	 */
	function ListbuilderHandler() {
		parent::GridHandler();
	}


	//
	// Getters and Setters
	//
	/**
	 * Get possible items for left-hand drop-down list.
	 * @return array
	 */
	function getPossibleItemList() {
		return $this->_possibleItems;
	}

	/**
	 * Set possible items for left-hand drop-down list.
	 * @param $possibleItems array
	 */
	function setPossibleItemList($possibleItems) {
		$this->_possibleItems = $possibleItems;
	}

	/**
	 * Get the listbuilder template.
	 * @return string
	 */
	function getTemplate() {
		if (is_null($this->_template)) {
			$this->setTemplate('controllers/listbuilder/listbuilder.tpl');
		}

		return $this->_template;
	}

	/**
	 * Set the title for the source (left side of the listbuilder)
	 * @param $sourceTitle string
	 */
	function setSourceTitle($sourceTitle) {
		$this->_sourceTitle = $sourceTitle;
	}

	/**
	 * Get the title for the source (left side of the listbuilder)
	 * @return string
	 */
	function getSourceTitle() {
		return $this->_sourceTitle;
	}

	/**
	 * Set the type of source (Free text input, select from list, autocomplete)
	 * @param $sourceType int
	 */
	function setSourceType($sourceType) {
		$this->_sourceType = $sourceType;
	}

	/**
	 * Get the type of source (Free text input, select from list, autocomplete)
	 * @return int
	 */
	function getSourceType() {
		return $this->_sourceType;
	}

	/**
	 * Set the ListbuilderItem associated with this class
	 * @param $items array
	 */
	function setItems($items) {
		$this->_items = $items;
	}

	/**
	 * Return all ListbuilderItems
	 * @return array
	 */
	function getItems() {
		return $this->_items;
	}

	/**
	 * Return a ListbuilderItem by ID
	 * @return ListbuilderItem
	 */
	function getItem($itemId) {
		return $this->_items[$itemId];
	}

	/**
	 * Remove a ListbuilderItem by ID
	 * @param $itemId mixed
	 */
	function removeItem($itemId) {
		unset($this->_items[$itemId]);
	}

	/**
	 * Set the localized label for the list (right side of the listbuilder)
	 * @param $listTitle string
	 */
	function setListTitle($listTitle) {
		$this->_listTitle = $listTitle;
	}

	/**
	 * Get the localized label for the list (right side of the listbuilder)
	 * @return string
	 */
	function getListTitle() {
		return $this->_listTitle;
	}

	/**
	 * Set the localized labels for each attribute
	 * @param $attributeNames array
	 */
	function setAttributeNames($attributeNames) {
		$this->_attributeNames = $attributeNames;
	}

	/**
	 * Get the localized labels for each attribute
	 * @return array
	 */
	function getAttributeNames() {
		return $this->_attributeNames;
	}

	/**
	 * Set additional data for the listbuilder
	 * @param $additionalData array
	 */
	function setAdditionalData($additionalData) {
		$this->_additionalData = $additionalData;
	}

	/**
	 * Get additional data for the listbuilder
	 * @return array
	 */
	function getAdditionalData() {
		return $this->_additionalData;
	}


	/**
	 * Display the Listbuilder
	 */
	function fetch(&$args, &$request, $additionalVars = null) {
		$templateMgr =& TemplateManager::getManager();
		$this->setupTemplate();
		$router =& $request->getRouter();

		if(isset($additionalVars)) {
			foreach ($additionalVars as $key => $value) {
				$templateMgr->assign($key, $value);
			}
		} else {
			$templateMgr->assign('addUrl', $router->url($request, array(), null, 'addItem'));
			$templateMgr->assign('deleteUrl', $router->url($request, array(), null, 'deleteItems'));
		}

		// Translate modal submit/cancel buttons
		$okButton = Locale::translate('common.ok');
		$warning = Locale::translate('common.warning');
		$templateMgr->assign('localizedButtons', "$okButton, $warning");

		// initialize to create the columns
		$columns =& $this->getColumns();
		$templateMgr->assign_by_ref('columns', $columns);
		$templateMgr->assign('numColumns', count($columns));

		// Render the rows
		// FIXME: Using a private method in a sub-class is not allowed. This is duplicate
		// code anyway so factor it into it's own method in the base class.
		$elements = $this->getGridDataElements($request);
		$rows = $this->_renderRowsInternally($request, $elements);
		$templateMgr->assign_by_ref('rows', $rows);

		$templateMgr->assign('listbuilder', $this);

		$json = new JSON(true, $templateMgr->fetch($this->getTemplate()));
		return $json->getString();
	}


	//
	// Overridden methods from GridHandler
	//
	/**
	 * @see GridHandler::getRowInstance()
	 * @return CitationGridRow
	 */
	function &getRowInstance() {
		// Return a citation row
		$row = new ListbuilderGridRow();
		return $row;
	}


	//
	// Overridden methods from PKPHandler
	//
	/**
	 * @see PKPHandler::setupTemplate()
	 */
	function setupTemplate() {
		parent::setupTemplate();

		Locale::requireComponents(array(LOCALE_COMPONENT_APPLICATION_COMMON, LOCALE_COMPONENT_OMP_MANAGER, LOCALE_COMPONENT_PKP_MANAGER));
	}


	//
	// Abstract protected methods to be implemented by subclasses.
	//
	/**
	 * Handle adding an item to the list
	 * NB: sub-classes must implement this method.
	 */
	function addItem(&$args, &$request) {
		assert(false);
	}

	/**
	 * Handle deleting items from the list
	 * NB: sub-classes must implement this method.
	 */
	function deleteItems(&$args, &$request) {
		assert(false);
	}


	//
	// Protected helper methods
	//
	/**
	 * Retrieves the added item from the request and
	 * checks whether it points to an item that is part of
	 * the possible item list.
	 * FIXME: This should be called by default in all list builders
	 * whenever we add items, see #6193. This method can then
	 * probably be made private.
	 * @param $args array
	 * @return string
	 */
	function getAddedItemId($args) {
		// Retrieve the item id.
		$rowId = "selectList-" . $this->getId();
		$itemId = (int)$args[$rowId];
		if(!isset($itemId)) fatalError('Missing item id!');

		// Check whether the item id is part of the
		// possible items.
		$possibleItems =& $this->getPossibleItemList();
		if (!isset($possibleItems[$itemId])) fatalError('Trying to add an item that is not part of the possible items!');

		return $itemId;
	}

	/**
	 * Retrieve a list of items from the request that were
	 * selected for deletion and checks whether these items
	 * are actually part of the list.
	 * FIXME: This should be called by default in all list builders
	 * whenever we add/delete items, see #6193. This method can then
	 * probably be made private.
	 * @param $request Request
	 * @param $args array
	 * @param $numInitialArgs integer The number of request arguments
	 *  used to call the list builder. This is required because the
	 *  deleted elements will be at the end of the argument list.
	 *  FIXME: Implement the deleted parameters as a proper array
	 *  with it's own parameter name, see #6193.
	 * @return array An array of strings.
	 */
	function getDeletedItemIds(&$request, $args, $numInitialArgs) {
		$rowIds = array_splice($args, $numInitialArgs + 1);
		$availbaleItems =& $this->getGridDataElements($request);
		$items = array();
		foreach($rowIds as $rowId) {
			$itemId = (int)array_pop(explode('-', $rowId));
			if (!isset($availbaleItems[$itemId])) fatalError('Trying to delete an item that is not on the list!');
			$items[] = $itemId;
		}
		return $items;
	}
}
?>
