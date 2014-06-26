<?php

/**
 * @file classes/submission/SubmissionFileDAODelegate.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileDAODelegate
 * @ingroup submission
 * @see SubmissionFile
 *
 * @brief Abstract class to support DAO delegates that provide operations
 *  to retrieve and modify SubmissionFile objects.
 */

import('lib.pkp.classes.db.DAO');

class SubmissionFileDAODelegate extends DAO {
	/**
	 * Constructor
	 */
	function SubmissionFileDAODelegate() {
		parent::DAO();
	}


	//
	// Abstract public methods to be implemented by subclasses.
	//
	/**
	 * Return the name of the base submission entity
	 * (i.e. 'monograph', 'paper', 'article', etc.)
	 * @return string
	 */
	function getSubmissionEntityName() {
		assert(false);
	}

	/**
	 * Insert a new submission file.
	 * @param $submissionFile SubmissionFile
	 * @param $sourceFile string The place where the physical file
	 *  resides right now or the file name in the case of an upload.
	 *  The file will be copied to its canonical target location.
	 * @param $isUpload boolean set to true if the file has just been
	 *  uploaded.
	 * @return SubmissionFile the inserted file
	 */
	function &insertObject(&$submissionFile, $sourceFile, $isUpload = false) {
		assert(false);
	}

	/**
	 * Update a submission file.
	 * @param $submissionFile SubmissionFile The target state
	 *  of the updated file.
	 * @param $previousFile SubmissionFile The current state
	 *  of the updated file.
	 * @return boolean
	 */
	function updateObject(&$submissionFile, &$previousFile) {
		assert(false);
	}

	/**
	 * Delete a submission file from the database.
	 * @param $submissionFile SubmissionFile
	 * @return boolean
	 */
	function deleteObject(&$submissionFile) {
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
	 * Get the list of fields for which data is localized.
	 * @return array
	 */
	function getLocaleFieldNames() {
		return parent::getLocaleFieldNames();
	}

	/**
	 * Update the localized fields for this submission file.
	 * @param $submissionFile SubmissionFile
	 */
	function updateLocaleFields(&$submissionFile) {
		// Update the locale fields.
		$this->updateDataObjectSettings($this->getSubmissionEntityName().'_file_settings', $submissionFile, array(
			'file_id' => $submissionFile->getFileId()
		));
	}
}

?>
