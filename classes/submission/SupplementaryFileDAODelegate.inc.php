<?php

/**
 * @file classes/submission/SupplementaryFileDAODelegate.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
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


	//
	// Public methods
	//
	/**
	 * @see SubmissionFileDAODelegate::insert()
	 * @param $supplementaryFile SupplementaryFile
	 * @return SupplementaryFile|null
	 */
	function insertObject($supplementaryFile, $sourceFile, $isUpload = false) {
		// First insert the data for the super-class.
		$supplementaryFile = parent::insertObject($supplementaryFile, $sourceFile, $isUpload);
		if (!$supplementaryFile) return null;

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
	 * @see SubmissionFileDAODelegate::update()
	 * @param $suppFile SupplementaryFile
	 * @param $previousFile SupplementaryFile
	 * @return boolean True if success.
	 */
	function updateObject($suppFile, $previousFile) {
		// Update the parent class table first.
		if (!parent::updateObject($suppFile, $previousFile)) return false;

		// Now update the supplementary file table.
		$this->update(
			'UPDATE submission_supplementary_files
			SET
				file_id = ?,
				revision = ?
			WHERE file_id = ? AND revision = ?',
			array(
				(int)$suppFile->getFileId(),
				(int)$suppFile->getRevision(),
				(int)$previousFile->getFileId(),
				(int)$previousFile->getRevision()
			)
		);
		return true;
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
				'creator', 'subject', 'description', 'publisher', 'sponsor', 'source',
			)
		);
	}

	/**
	 * @copydoc DAO::getAdditionalFieldNames()
	 */
	function getAdditionalFieldNames() {
		return array_merge(
			parent::getAdditionalFieldNames(),
			array(
				'dateCreated', 'language',
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


