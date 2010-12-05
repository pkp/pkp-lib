<?php

/**
 * @file classes/monograph/SubmissionFileDAODelegate.inc.php
 *
 * Copyright (c) 2003-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileDAODelegate
 * @ingroup submission
 * @see SubmissionFile
 *
 * @brief Abstract class to support DAO delegates that provide perations
 *  to retrieve and modify SubmissionFile objects.
 */


class SubmissionFileDAODelegate {
	/** @var SubmissionFileDAO a reference to the calling DAO */
	var $_submissionFileDao;

	/**
	 * Constructor
	 * @param $submissionFileDao SubmissionFileDAO
	 */
	function SubmissionFileDAODelegate(&$submissionFileDao) {
		$this->_submissionFileDao =& $submissionFileDao;
	}


	//
	// Getters and Setters
	//
	/**
	 * Get the submission file DAO.
	 * @return SubmissionFileDAO
	 */
	function &getSubmissionFileDAO() {
		return $this->_submissionFileDao;
	}


	//
	// Public methods
	//
	/**
	 * Get the list of fields for which data is localized.
	 * @return array
	 */
	function getLocaleFieldNames() {
		return array();
	}


	//
	// Abstract public methods to be implemented by subclasses.
	//
	/**
	 * Insert a new submission file.
	 * @param $submissionFile SubmissionFile
	 * @return SubmissionFile the inserted file
	 */
	function &insertObject(&$submissionFile) {
		assert(false);
	}

	/**
	 * Update a submission file.
	 * @param $submissionFile SubmissionFile
	 * @return boolean
	 */
	function updateObject(&$submissionFile) {
		assert(false);
	}

	/**
	 * Delete submission files from the database.
	 * @param $filterSql string an SQL where clause that works on
	 *  the submission file base class table to bulk-select all files
	 *  to be deleted.
	 * @param $params array the corresponding query parameters
	 * @param $latestOnly boolean true if only the last revision
	 *  of every matched file should be deleted.
	 * @return boolean
	 */
	function deleteObject($filterSql, $params, $latestOnly) {
		assert(false);
	}

	/**
	 * Function to return a SubmissionFile object from a row.
	 * @param $row array
	 * @return SubmissionFile
	 */
	function &fromRow(&$row) {
		assert(false);
	}

	/**
	 * Construct a new data object corresponding to this DAO.
	 * @return SubmissionFile
	 */
	function newDataObject() {
		assert(false);
	}


	//
	// Protected helper methods
	//
	/**
	 * Update the localized fields for this supp file.
	 * @param $submissionFile SubmissionFile
	 */
	function updateLocaleFields(&$submissionFile) {
		$submissionFileDao =& $this->getSubmissionFileDAO();

		// Configure the submission file DAO with the
		// locale field names adequate to the current
		// file implementation.
		$submissionFileDao->setLocaleFieldNames($this->getLocaleFieldNames());

		// Update the locale fields.
		$submissionFileDao->updateDataObjectSettings($submissionFileDao->getSubmissionEntityName().'_file_settings', $submissionFile, array(
			'file_id' => $submissionFile->getFileId()
		));
	}
}

?>