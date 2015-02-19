<?php

/**
 * @file classes/controllers/grid/citation/PKPCitationGridRow.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPCitationGridRow
 * @ingroup classes_controllers_grid_citation
 *
 * @brief The citation grid row definition
 */

import('lib.pkp.classes.controllers.grid.GridRow');

class PKPCitationGridRow extends GridRow {
	/** @var integer */
	var $_assocId;

	/** @var boolean */
	var $_isCurrentItem = false;

	/**
	 * Constructor
	 */
	function PKPCitationGridRow() {
		parent::GridRow();
	}


	//
	// Getters and Setters
	//
	/**
	 * Set the assoc id
	 * @param $assocId integer
	 */
	function setAssocId($assocId) {
		$this->_assocId = $assocId;
	}

	/**
	 * Get the assoc id
	 * @return integer
	 */
	function getAssocId() {
		return $this->_assocId;
	}

	/**
	 * Set the current item flag
	 * @param $isCurrentItem boolean
	 */
	function setIsCurrentItem($isCurrentItem) {
		$this->_isCurrentItem = $isCurrentItem;
	}

	/**
	 * Get the current item flag
	 * @return boolean
	 */
	function getIsCurrentItem() {
		return $this->_isCurrentItem;
	}


	//
	// Overridden methods from GridRow
	//
	/**
	 * @see GridRow::initialize()
	 */
	function initialize(&$request) {
		// Do the default initialization
		parent::initialize($request);

		// Retrieve the assoc id from the request
		$assocId = $request->getUserVar('assocId');
		assert(is_numeric($assocId));
		$this->setAssocId($assocId);

		// Is this a new row or an existing row?
		$rowId = $this->getId();
		if (!empty($rowId) && is_numeric($rowId)) {
			// Only add row actions if this is an existing row
			$router =& $request->getRouter();
			$this->addAction(
				new LegacyLinkAction(
					'deleteCitation',
					LINK_ACTION_MODE_CONFIRM,
					LINK_ACTION_TYPE_REMOVE,
					$router->url($request, null, null, 'deleteCitation', null,
							array('assocId' => $assocId, 'citationId' => $rowId)),
					'grid.action.delete', null, 'delete',
					__('submission.citations.editor.citationlist.deleteCitationConfirmation')
				),
				GRID_ACTION_POSITION_ROW_LEFT
			);
		}
	}

	/**
	 * @see GridRow::getCellActions()
	 */
	function getCellActions(&$request, &$column, $position = GRID_ACTION_POSITION_DEFAULT) {
		$cellActions = array();
		if ($position == GRID_ACTION_POSITION_DEFAULT) {
			// Is this a new row or an existing row?
			$rowId = $this->getId();
			if (!empty($rowId) && is_numeric($rowId)) {
				$citation =& $this->getData();
				assert(is_a($citation, 'Citation'));

				// We should never present citations to the user that have
				// not been checked already.
				if ($citation->getCitationState() < CITATION_PARSED) fatalError('Invalid citation!');

				// Instantiate the cell action.
				$router =& $request->getRouter();
				$cellActions = array(
					new LegacyLinkAction(
						'editCitation',
						LINK_ACTION_MODE_AJAX,
						LINK_ACTION_TYPE_GET,
						$router->url($request, null, null, 'editCitation', null,
								array('assocId' => $this->getAssocId(), 'citationId' => $rowId)),
						'submission.citations.editor.clickToEdit',
						null, null, null,
						'citationEditorDetailCanvas'
					)
				);
			}
		}
		return $cellActions;
	}
}

?>
