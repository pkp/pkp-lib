<?php

/**
 * @file controllers/grid/queries/RepresentationQueryNotesGridHandler.inc.php
 *
 * Copyright (c) 2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RepresentationQueryNotesGridHandler
 * @ingroup controllers_grid_query
 *
 * @brief base PKP class to handle query grid requests.
 */

import('lib.pkp.controllers.grid.queries.QueryNotesGridHandler');

class RepresentationQueryNotesGridHandler extends QueryNotesGridHandler {

	/**
	 * Constructor
	 */
	function RepresentationQueryNotesGridHandler() {
		parent::QueryNotesGridHandler();
	}


	//
	// Getters/Setters
	//
	/**
	 * Get the authorized representation.
	 * @return Representation
	 */
	function getRepresentation() {
		return $this->getAuthorizedContextObject(ASSOC_TYPE_REPRESENTATION);
	}


	//
	// Overridden methods from PKPHandler.
	// Note: this is subclassed in application-specific grids.
	//
	/**
	 * Get the arguments that will identify the data in the grid.
	 * Overridden by child grids.
	 * @return array
	 */
	function getRequestArgs() {
		return array_merge(
			parent::getRequestArgs(),
			array('representationId' => $this->getRepresentation()->getId())
		);
	}
}

?>
