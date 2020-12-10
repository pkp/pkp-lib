<?php
/**
 * @file classes/submission/RepresentationDAO.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RepresentationDAO
 * @ingroup submission
 * @see Representation
 *
 * @brief Abstract DAO for fetching/working with DB storage of Representation objects
 */

abstract class RepresentationDAO extends DAO {

	/**
	 * Retrieves a representation by ID.
	 * @param $representationId int Representation ID.
	 * @param $publicationId int Optional publication ID.
	 * @param $contextId int Optional context ID.
	 * @return DAOResultFactory
	 */
	abstract function getById($representationId, $publicationId = null, $contextId = null);

	/**
	* Retrieves an iterator of representations for a publication
	* @param $publicationId int
	* @param $contextId int
	* @return DAOResultFactory
	*/
	abstract function getByPublicationId($publicationId, $contextId = null);
}


