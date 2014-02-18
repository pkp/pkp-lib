<?php
/**
 * @file classes/submission/RepresentationDAO.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RepresentationDAO
 * @ingroup submission
 * @see Representation
 *
 * @brief Abstract DAO for fetching/working with DB storage of Representation objects
 */

class RepresentationDAO extends DAO {
	/**
	 * Constructor
	 */
	function RepresentationDAO() {
		parent::DAO();
	}

	/**
	 * Retrieves an iterator of representations for a submission
	 * @param int $submissionId int
	 * @return DAOResultFactory
	 */
	function getBySubmissionId($submissionId) {
		assert(false); // To be implemented by subclasses
	}
}

?>
