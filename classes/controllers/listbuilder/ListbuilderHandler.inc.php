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
import('lib.pkp.classes.controllers.listbuilder.ListbuilderGridColumn');

define_exposed('LISTBUILDER_SOURCE_TYPE_TEXT', 0);
define_exposed('LISTBUILDER_SOURCE_TYPE_SELECT', 1);
define_exposed('LISTBUILDER_SOURCE_TYPE_BOUND', 2);

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
	/** @var integer Definition of the type of source LISTBUILDER_SOURCE_TYPE_... **/
	var $_sourceType;


	/**
	 * Constructor.
	 */
	function ListbuilderHandler() {
		parent::GridHandler();
	}

	/**
	 * @see GridHandler::initialize
	 */
	function initialize(&$request) {
		parent::initialize($request);

		import('lib.pkp.classes.linkAction.request.NullAction');
		$this->addAction(
			new LinkAction(
				'addItem',
				new NullAction(),
				__('grid.action.addItem'),
				'add_item'
			)
		);
	}


	//
	// Getters and Setters
	//
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
	 * Set the type of source (Free text input, select from list, autocomplete)
	 * @param $sourceType int LISTBUILDER_SOURCE_TYPE_...
	 */
	function setSourceType($sourceType) {
		$this->_sourceType = $sourceType;
	}

	/**
	 * Get the type of source (Free text input, select from list, autocomplete)
	 * @return int LISTBUILDER_SOURCE_TYPE_...
	 */
	function getSourceType() {
		return $this->_sourceType;
	}

	/**
	 * Delete an entry.
	 * @param $rowId mixed ID of row to modify
	 * @return boolean
	 */
	function deleteEntry($rowId) {
		fatalError('ABSTRACT METHOD');
	}

	/**
	 * Persist an update to an entry.
	 * @param $rowId mixed ID of row to modify
	 * @param $existingEntry mixed Existing entry to be modified
	 * @param $newEntry mixed New entry with changes to persist
	 * @return boolean
	 */
	function updateEntry($rowId, $existingEntry, $newEntry) {
		// This may well be overridden by a subclass to modify
		// an existing entry, e.g. to maintain referential integrity.
		// If not, we can simply delete and insert.
		if (!$this->deleteEntry($rowId)) return false;
		return $this->insertEntry($newEntry);
	}

	/**
	 * Persist a new entry insert.
	 * @param $entry mixed New entry with data to persist
	 * @return boolean
	 */
	function insertEntry($entry, &$request) {
		fatalError('ABSTRACT METHOD');
	}

	/**
	 * Fetch the options for a LISTBUILDER_SOURCE_TYPE_SELECT LB
	 * Should return a multidimensional array:
	 * array(
	 * 	array('column 1 option 1', 'column 2 option 1'),
	 * 	array('column 1 option 2', 'column 2 option 2'
	 * );
	 * @return array
	 */
	function getOptions() {
		fatalError('ABSTRACT METHOD');
	}

	//
	// Publicly (remotely) available listbuilder functions
	//
	/**
	 * Fetch the listbuilder.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function fetch($args, &$request) {
		return $this->fetchGrid($args, $request);
	}

	/**
	 * Save the listbuilder.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function save($args, &$request) {
		import('lib.pkp.classes.core.JSONManager');

		// The ListbuilderHandler will post a list of changed
		// data in the "data" post var. Need to go through it
		// and reconcile the data against this list, adding/
		// updating/deleting as needed.
		$jsonManager = new JSONManager();
		$changedData = $jsonManager->decode($request->getUserVar('data'));

		// 1. Check that all modified entries actually exist
		$data =& $this->getGridDataElements(&$request);
		foreach ($changedData as $entry) {
			// Skip new entries
			if (!isset($entry->rowId)) continue;

			$rowId = $entry->rowId;
			if (!isset($data[$rowId])) fatalError('Nonexistent element modified!');
		}

		// 2. Make the changes.
		foreach ($changedData as $entry) {
			// Update an existing entry
			if (isset($entry->rowId)) {
				$rowId = $entry->rowId;
				unset($entry->rowId);
				if (!$this->updateEntry($rowId, $data[$rowId], $entry)) {
					// Failure; abort.
					$json = new JSONMessage(false);
					return $json->getString();

				}
			} else {
				// Insert a new entry
				if (!$this->insertEntry($entry)) {
					// Failure; abort.
					$json = new JSONMessage(false);
					return $json->getString();
				}
			}
		}

		// Report a successful save.
		$json = new JSONMessage(true);
		return $json->getString();
	}


	/**
	 * Load the set of options for a select list type listbuilder.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function fetchOptions($args, &$request) {
		$options = $this->getOptions();
		$json = new JSONMessage(true, $options);
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
}

?>
