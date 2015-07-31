<?php

/**
 * @file controllers/grid/queries/RepresentationQueriesGridCellProvider.inc.php
 *
 * Copyright (c) 2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RepresentationQueriesGridCellProvider
 * @ingroup controllers_grid_users_author
 *
 * @brief Base class for a cell provider that can retrieve labels for representation queries.
 */

import('lib.pkp.classes.controllers.grid.DataObjectGridCellProvider');

class RepresentationQueriesGridCellProvider extends QueriesGridCellProvider {

	/** @var Representation */
	var $_representation;

	/**
	 * Constructor
	 * @param $submission Submission
	 * @param $representation Representation
	 * @param $stageId int
	 * @param $canManage boolean True iff the user can manage the query.
	 */
	function RepresentationQueriesGridCellProvider($submission, $representation, $stageId, $canManage) {
		parent::QueriesGridCellProvider($submission, $stageId, $canManage);
		$this->_representation = $representation;
	}

	//
	// Template methods from GridCellProvider
	//
	/**
	 * Get request arguments.
	 * @param $row GridRow
	 * @return array
	 */
	function getRequestArgs($row) {
		return array_merge(
			parent::getRequestArgs($row),
			array(
				'representationId' => $this->_representation->getId(),
			)
		);
	}
}

?>
