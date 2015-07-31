<?php

/**
 * @file controllers/grid/queries/RepresentationQueriesGridHandler.inc.php
 *
 * Copyright (c) 2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RepresentationQueriesGridHandler
 * @ingroup controllers_grid_query
 *
 * @brief Handle query grid requests for representation queries.
 */

// Link action & modal classes
import('lib.pkp.controllers.grid.queries.QueriesGridHandler');

class RepresentationQueriesGridHandler extends QueriesGridHandler {

	/**
	 * Constructor
	 */
	function RepresentationQueriesGridHandler() {
		parent::QueriesGridHandler();
	}


	//
	// Getters/Setters
	//
	/**
	 * Get the query assoc type.
	 * @return int ASSOC_TYPE_...
	 */
	function getAssocType() {
		return ASSOC_TYPE_REPRESENTATION;
	}

	/**
	 * Get the query assoc ID.
	 * @return int
	 */
	function getAssocId() {
		return $this->getRepresentation()->getId();
	}

	/**
	 * Get the representation this grid relates to.
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
	 * @copydoc GridHandler::getRowInstance()
	 * @return QueriesGridRow
	 */
	function getRowInstance() {
		import('lib.pkp.controllers.grid.queries.RepresentationQueriesGridRow');
		return new RepresentationQueriesGridRow($this->getSubmission(), $this->getRepresentation(), $this->getStageId(), $this->getCanManage());
	}

	/**
	 * Get the arguments that will identify the data in the grid.
	 * Overridden by child grids.
	 * @return array
	 */
	function getRequestArgs() {
		return array_merge(
			parent::getRequestArgs(),
			array(
				'representationId' => $this->getRepresentation()->getId(),
			)
		);
	}

	/**
	 * Create and return a data provider for this grid.
	 * @return GridCellProvider
	 */
	function getCellProvider() {
		import('lib.pkp.controllers.grid.queries.RepresentationQueriesGridCellProvider');
		return new RepresentationQueriesGridCellProvider($this->getSubmission(), $this->getRepresentation(), $this->getStageId(), $this->getCanManage());
	}

	/**
	 * Get the name of the query notes grid handler.
	 * @return string
	 */
	function getQueryNotesGridHandlerName() {
		return 'grid.queries.RepresentationQueryNotesGridHandler';
	}

}

?>
