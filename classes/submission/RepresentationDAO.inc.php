<?php
/**
 * @file classes/submission/RepresentationDAO.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RepresentationDAO
 * @ingroup submission
 * @see Representation
 *
 * @brief Abstract DAO for fetching/working with DB storage of Representation objects
 */

abstract class RepresentationDAO extends DAO {
	/**
	 * Constructor
	 */
	function RepresentationDAO() {
		parent::DAO();
	}

	/**
	 * Retrieves a representation by ID.
	 * @param $representationId int Representation ID.
	 * @param $submissionId int Optional submission ID.
	 * @param $contextId int Optional context ID.
	 * @return DAOResultFactory
	 */
	abstract function getById($representationId, $submissionId = null, $contextId = null);

	/**
	 * Retrieves an iterator of representations for a submission
	 * @param $submissionId int
	 * @return DAOResultFactory
	 */
	abstract function getBySubmissionId($submissionId);
}

?>
