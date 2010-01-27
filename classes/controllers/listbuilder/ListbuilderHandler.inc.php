<?php

/**
 * @file classes/controllers/listbuilder/ListbuilderHandler.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Listbuilder
 * @ingroup controllers\_listbuilder
 *
 * @brief Class defining basic operations for handling Listbuilder UI elements
 */

import('controllers.grid.GridMainHandler');
import('core.JSON');

define('LISTBUILDER_SOURCE_TYPE_TEXT', 0);
define('LISTBUILDER_SOURCE_TYPE_SELECT', 1);
define('LISTBUILDER_SOURCE_TYPE_BOUND', 2);

class ListbuilderHandler extends GridMainHandler {
	/** The title of the overall list builder.  **/
	var $title;

	/** Internal identifier for the Listbuilder. To be used as a programmatic reference. */
	var $id;

	/** The label associated with the primary source to be added to the list **/
	var $sourceTitle;

	/** Definition of the type of source **/
	var $sourceType;

	/** The current collection of items in the list **/
	var $items;

	/** The title of the item collection **/
	var $listTitle;

	/** Array of optional attributes **/
	var $attributeNames;

	/* Array of strings containing possible items that are stored in the source list */
	var $possibleItems;

	/**
	 * Constructor.
	 */
	function ListbuilderHandler() {
		parent::GridMainHandler();
	}

	function getRemoteOperations() {
		return array('fetch', 'additem', 'deleteitems', 'getlist', 'getautocompletesource');
	}

	/**
	 * Set the title for the source (left side of the listbuilder)
	 * @param sourceTitle string
	 */
	function setSourceTitle($sourceTitle) {
		$this->sourceTitle = $sourceTitle;
	}

	/**
	 * Get the title for the source (left side of the listbuilder)
	 * @return string
	 */
	function getSourceTitle() {
		return $this->sourceTitle;
	}

	/**
	 * Set the type of source (Free text input, select from list, autocomplete)
	 * @param sourceType int
	 */
	function setSourceType($sourceType) {
		$this->sourceType = $sourceType;
	}

	/**
	 * Get the type of source (Free text input, select from list, autocomplete)
	 * @return int
	 */
	function getSourceType() {
		return $this->sourceType;
	}

	/**
	 * Set the ListbuilderItem associated with this class
	 * @param items array
	 */
	function setItems($items) {
		$this->items = $items;
	}

	/**
	 * Return all ListbuilderItems
	 * @return array
	 */
	function getItems() {
		return $this->items;
	}

	/**
	 * Return a ListbuilderItem by ID
	 * @return ListbuilderItem
	 */
	function getItem($itemId) {
		return $this->items[$itemId];
	}

	/**
	 * Remove a ListbuilderItem by ID
	 * @param itemId mixed
	 */
	function removeItem($itemId) {
		unset($items[$itemId]);
	}

	/**
	 * Set the localized label for the list (right side of the listbuilder)
	 * @param listTitle string
	 */
	function setListTitle($listTitle) {
		$this->listTitle = $listTitle;
	}

	/**
	 * Get the localized label for the list (right side of the listbuilder)
	 * @return string
	 */
	function getListTitle() {
		return $this->listTitle;
	}

	/**
	 * Set the localized labels for each attribute
	 * @param attributeNames array
	 */
	function setAttributeNames($attributeNames) {
		$this->attributeNames = $attributeNames;
	}

	/**
	 * Get the localized labels for each attribute
	 * @return array
	 */
	function getAttributeNames() {
		return $this->attributeNames;
	}

	/**
 	 * Build a list of <option>'s based on the input (can be array or one list item)
	 * @param itemName string
	 * @param attributeNames string
	 */
	function buildListItemHTML($itemId, $itemName, $attributeNames) {
		if (isset($attributeNames)) {
			if (is_array($attributeNames)) $attributeNames = implode(', ', $attributeNames);
			return "<option value='$itemId'>$itemName ($attributeNames)</option>";
		}

		return "<option value='$itemId'>$itemName</option>";
	}


	/**
	 * Display the Listbuilder
	 */
	function fetch(&$args, &$request) {
		// FIXME: User validation

		$templateMgr =& TemplateManager::getManager();
		$this->setupTemplate();
		$router =& $request->getRouter();

		// Let the subclass configure the listbuilder
		$this->initialize($request);

		$templateMgr->assign('addUrl', $router->url($request, array(), null, 'additem'));
		$templateMgr->assign('deleteUrl', $router->url($request, array(), null, 'deleteitems'));
		$templateMgr->assign('autocompleteUrl', $router->url($request, array(), null, 'getautocompletesource'));

		// Translate modal submit/cancel buttons
		$okButton = Locale::translate('common.ok');
		$warning = Locale::translate('common.warning');
		$templateMgr->assign('localizedButtons', "$okButton, $warning");

		$rowHandler =& $this->getRowHandler();
		// initialize to create the columns
		$rowHandler->initialize($request);
		$columns =& $rowHandler->getColumns();
		$templateMgr->assign_by_ref('columns', $columns);
		$templateMgr->assign('numColumns', count($columns));

		// Render the rows
		$rows = $this->_renderRowsInternally($request);
		$templateMgr->assign_by_ref('rows', $rows);

		$templateMgr->assign('listbuilder', $this);
		echo $templateMgr->fetch('controllers/listbuilder/listbuilder.tpl');
    }

	function setupTemplate() {
		parent::setupTemplate();

		Locale::requireComponents(array(LOCALE_COMPONENT_APPLICATION_COMMON, LOCALE_COMPONENT_OMP_MANAGER, LOCALE_COMPONENT_PKP_MANAGER));
	}
}

?>
