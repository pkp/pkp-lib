<?php

/**
 * @file classes/submission/SupplementaryFileDAODelegate.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SupplementaryFileDAODelegate
 * @ingroup submission
 * @see SupplementaryFile
 *
 * @brief Base class for operations for retrieving and modifying SupplementaryFile objects.
 *
 * The SubmissionFileDAO will delegate to this class if it wishes
 * to access SupplementaryFile classes.
 */

import('lib.pkp.classes.submission.SubmissionFileDAODelegate');
import('lib.pkp.classes.submission.SupplementaryFile');

class SupplementaryFileDAODelegate extends SubmissionFileDAODelegate {
	/**
	 * Constructor
	 */
	function SupplementaryFileDAODelegate() {
		parent::SubmissionFileDAODelegate();
	}


	//
	// Public methods
	//
	/**
	 * @see SubmissionFileDAODelegate::insert()
	 * @param $supplementaryFile SupplementaryFile
	 * @return SupplementaryFile
	 */
	function insertObject($supplementaryFile, $sourceFile, $isUpload = false) {
		// First insert the data for the super-class.
		$supplementaryFile = parent::insertObject($supplementaryFile, $sourceFile, $isUpload);
		if (is_null($supplementaryFile)) return $supplementaryFile;

		// Now insert the supplementary-specific data.
		$this->update(
			'INSERT INTO submission_supplementary_files
				(file_id, revision)
			VALUES
				(?, ?)',
			array(
				(int) $supplementaryFile->getFileId(),
				(int) $supplementaryFile->getRevision(),
			)
		);

		return $supplementaryFile;
	}

	/**
	 * @copydoc SubmissionFileDAODelegate::deleteObject()
	 */
	function deleteObject($submissionFile) {
		// First delete the submission file entry.
		if (!parent::deleteObject($submissionFile)) return false;

		// Delete the supplementary file entry.
		$this->update(
			'DELETE FROM submission_supplementary_files
			 WHERE file_id = ? AND revision = ?',
			array(
				(int) $submissionFile->getFileId(),
				(int) $submissionFile->getRevision()
			)
		);
		return true;
	}

	/**
	 * @copydoc DAO::getLocaleFieldNames()
	 */
	function getLocaleFieldNames() {
		return array_merge(
			parent::getLocaleFieldNames(),
			array(
				'creator',
			)
		);
	}

	/**
	 * @copydoc SubmissionFileDAODelegate::newDataObject()
	 */
	function newDataObject() {
		return new SupplementaryFile();
	}
}

?>
