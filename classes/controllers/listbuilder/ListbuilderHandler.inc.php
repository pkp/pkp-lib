<?php

/**
 * @file classes/controllers/listbuilder/ListbuilderHandler.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Listbuilder
 * @ingroup controllers_listbuilder
 *
 * @brief Class defining basic operations for handling Listbuilder UI elements
 */

import('controllers.grid.GridHandler');
import('controllers.listbuilder.ListbuilderGridRow');

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

	/** @var array Array of strings containing possible items that are stored in the source list */
	var $_possibleItems;

	/**
	 * Constructor.
	 */
	function ListbuilderHandler() {
		parent::GridHandler();
	}

	function getRemoteOperations() {
		return array('fetch', 'addItem', 'deleteItems');
	}

	/**
	 * Get the listbuilder template
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
	 * FIXME: AFAIK doxygen needs the $ to correctly parse variable names
	 *  I've corrected this throughout the code but leave this as a marker
	 *  for you.
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
 	 * Build a list of <option>'s based on the input (can be array or one list item)
	 * @param $itemName string
	 * @param $attributeNames string
	 */
	function _buildListItemHTML($itemId = null, $itemName = null, $attributeNames = null) {
		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign('itemId', $itemId);
		$templateMgr->assign('itemName', $itemName);

		if (isset($attributeNames)) {
			if (is_array($attributeNames)) $attributeNames = implode(', ', $attributeNames);
			$templateMgr->assign('attributeNames', $attributeNames);
		}

		return $templateMgr->fetch('controllers/listbuilder/listbuilderItem.tpl');
	}


	/**
	 * Display the Listbuilder
	 */
	function fetch(&$args, &$request) {
		// FIXME: User validation

		$templateMgr =& TemplateManager::getManager();
		$this->setupTemplate();
		$router =& $request->getRouter();

		$templateMgr->assign('addUrl', $router->url($request, array(), null, 'addItem'));
		$templateMgr->assign('deleteUrl', $router->url($request, array(), null, 'deleteItems'));

		// Translate modal submit/cancel buttons
		$okButton = __('common.ok');
		$warning = __('common.warning');
		$templateMgr->assign('localizedButtons', "$okButton, $warning");

		// initialize to create the columns
		$columns =& $this->getColumns();
		$templateMgr->assign_by_ref('columns', $columns);
		$templateMgr->assign('numColumns', count($columns));

		// Render the rows
		$rows = $this->_renderRowsInternally($request);
		$templateMgr->assign_by_ref('rows', $rows);

		$templateMgr->assign('listbuilder', $this);

		echo $templateMgr->fetch($this->getTemplate());
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

	/**
	 * @see PKPHandler::setupTemplate()
	 */
	function setupTemplate() {
		parent::setupTemplate();

		AppLocale::requireComponents(array(LOCALE_COMPONENT_APPLICATION_COMMON, LOCALE_COMPONENT_OMP_MANAGER, LOCALE_COMPONENT_PKP_MANAGER));
	}
}

?>
