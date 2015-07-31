<?php

/**
 * @file controllers/grid/queries/RepresentationQueriesGridRow.inc.php
 *
 * Copyright (c) 2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RepresentationQueriesGridRow
 * @ingroup controllers_grid_queries
 *
 * @brief Base class for query grid row definition
 */

import('lib.pkp.controllers.grid.queries.QueriesGridRow');

class RepresentationQueriesGridRow extends QueriesGridRow {

	/** @var Representation The representation for this row to relate to */
	var $_representation;

	/**
	 * Constructor
	 * @param $submission Submission
	 * @param $representation Representation
	 * @param $stageId int
	 * @param $canManage boolean True iff the user can manage the query.
	 */
	function RepresentationQueriesGridRow($submission, $representation, $stageId, $canManage) {
		parent::QueriesGridRow($submission, $stageId, $canManage);
		$this->_representation = $representation;
	}


	//
	// Overridden methods from GridRow
	//
	/**
	 * Get the base arguments that will identify the data in the grid.
	 * @return array
	 */
	function getRequestArgs() {
		return array_merge(
			parent::getRequestArgs(),
			array(
				'representationId' => $this->_representation->getId(),
			)
		);
	}
}

?>
