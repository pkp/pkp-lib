<?php

/**
 * @file controllers/grid/citation/CitationGridRow.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CitationGridRow
 * @ingroup controllers_grid_citation
 *
 * @brief Citation grid row definition
 */

import('controllers.grid.GridRow');

class CitationGridRow extends GridRow {
	/**
	 * Constructor
	 */
	function CitationGridRow() {
		parent::GridRow();
	}

	//
	// Overridden methods from GridRow
	//
	/**
	 * @see GridRow::initialize()
	 * @param $request PKPRequest
	 */
	function initialize(&$request) {
		// Do the default initialization
		parent::initialize($request);

		// Retrieve the article id from the request
		$articleId = $request->getUserVar('articleId');
		assert(is_numeric($articleId));

		// Is this a new row or an existing row?
		$rowId = $this->getId();
		if (!empty($rowId) && is_numeric($rowId)) {
			// Only add row actions if this is an existing row
			$router =& $request->getRouter();
			$actionArgs = array(
				'articleId' => $articleId,
				'citationId' => $rowId
			);

			// Get the citation to decide whether it has already been
			// checked.
			$citation =& $this->getData();
			assert(is_a($citation, 'Citation'));
			if ($citation->getCitationState() < CITATION_LOOKED_UP) {
				$editActionOp = 'checkCitation';
				$editActionTitle = 'submission.citations.grid.editCheckCitation';
			} else {
				$editActionOp = 'editCitation';
				$editActionTitle = 'grid.action.edit';
			}

			// Add row actions
			$this->addAction(
				new GridAction(
					'editCitation',
					GRID_ACTION_MODE_MODAL,
					GRID_ACTION_TYPE_REPLACE,
					$router->url($request, null, null, $editActionOp, null, $actionArgs),
					$editActionTitle,
					null,
					'edit'
				)
			);
			$this->addAction(
				new GridAction(
					'deleteCitation',
					GRID_ACTION_MODE_CONFIRM,
					GRID_ACTION_TYPE_REMOVE,
					$router->url($request, null, null, 'deleteCitation', null, $actionArgs),
					'grid.action.delete',
					null,
					'delete'
				)
			);

			// Set a non-default template that supports row actions
			$this->setTemplate('controllers/grid/gridRowWithActions.tpl');
		}
	}
}
