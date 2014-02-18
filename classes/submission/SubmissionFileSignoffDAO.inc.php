<?php

/**
 * @file classes/submission/SubmissionFileSignoffDAO.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileSignoffDAO
 * @ingroup submission
 * @see SignoffDAO
 *
 * @brief Extension of SignoffDAO to work with signoffs relating to submission
 * files.
 */

import('lib.pkp.classes.signoff.SignoffDAO');

class SubmissionFileSignoffDAO extends SignoffDAO {
	/**
	 * Constructor.
	 */
	function SubmissionFileSignoffDAO() {
		parent::SignoffDAO();
	}


	//
	// Public methods
	//
	/**
	 * @see SignoffDAO::getById
	 */
	function getById($signoffId) {
		return parent::getById($signoffId, ASSOC_TYPE_SUBMISSION_FILE);
	}

	/**
	 * Fetch a signoff by symbolic info, building it if needed.
	 * @param $symbolic string
	 * @param $submissionFileId int
	 * @param $userId int
	 * @param $stageId int
	 * @param $userGroupId int
	 * @param $fileId int
	 * @param $fileRevision int
	 * @return Signoff
	 */
	function build($symbolic, $submissionFileId, $userId = null,
			$userGroupId = null, $fileId = null, $fileRevision = null) {
		return parent::build(
			$symbolic,
			ASSOC_TYPE_SUBMISSION_FILE, $submissionFileId,
			$userId, $userGroupId,
			$fileId, $fileRevision
		);
	}

	/**
	 * Determine if a signoff exists
	 * @param string $symbolic
	 * @param int $submissionFileId
	 * @param int $stageId
	 * @param int $userGroupId
	 * @return boolean
	 */
	function signoffExists($symbolic, $submissionFileId, $userId = null, $userGroupId = null) {
		return parent::signoffExists($symbolic, ASSOC_TYPE_SUBMISSION_FILE, $userId, $userGroupId);
	}

	/**
	 * @see SignoffDAO::newDataObject
	 */
	function newDataObject() {
		$signoff = parent::newDataObject();
		$signoff->setAssocType(ASSOC_TYPE_SUBMISSION_FILE);
		return $signoff;
	}

	/**
	 * Retrieve the first signoff matching the specified symbolic name and
	 * submission file info.
	 * @param $symbolic string
	 * @param $submissionFileId int
	 * @param $userId int
	 * @param $stageId int
	 * @param $userGroupId int
	 * @param $fileId int
	 * @param $fileRevision int
	 * @return Signoff
	 */
	function getBySymbolic($symbolic, $submissionFileId, $userId = null,
			$userGroupId = null, $fileId = null, $fileRevision = null) {
		return parent::getBySymbolic(
			$symbolic,
			ASSOC_TYPE_SUBMISSION_FILE, $submissionFileId,
			$userId, $userGroupId,
			$fileId, $fileRevision
		);
	}

	/**
	 * Retrieve all signoffs matching the specified input parameters
	 * @param $symbolic string
	 * @param $submissionFileId int
	 * @param $userId int
	 * @param $stageId int
	 * @param $userGroupId int
	 * @return DAOResultFactory
	 */
	function getAllBySymbolic($symbolic, $submissionFileId = null, $userId = null, $userGroupId = null) {
		return parent::getAllBySymbolic($symbolic, ASSOC_TYPE_SUBMISSION_FILE, $submissionFileId, $userId, $userGroupId);
	}

	/**
	 * Retrieve all signoffs matching the specified input parameters
	 * @param $submissionId int
	 * @param $symbolic string (optional)
	 * @param $userId int
	 * @param $userGroupId int
	 * @param $onlyCompleted boolean
	 * @return DAOResultFactory
	 */
	function getAllBySubmission($submissionId, $symbolic = null, $userId = null, $userGroupId = null, $notCompletedOnly = false) {
		$sql = 'SELECT s.* FROM signoffs s, submission_files sf WHERE s.assoc_type = ? AND s.assoc_id = sf.file_id AND sf.submission_id = ?';
		$params = array(ASSOC_TYPE_SUBMISSION_FILE, (int) $submissionId);

		if ($symbolic) {
			$sql .= ' AND s.symbolic = ?';
			$params[] = $symbolic;
		}
		if ($userId) {
			$sql .= ' AND user_id = ?';
			$params[] = (int) $userId;
		}

		if ($userGroupId) {
			$sql .= ' AND user_group_id = ?';
			$params[] = (int) $userGroupId;
		}

		if ($notCompletedOnly) {
			$sql .= ' AND date_completed IS NULL';
		}

		$result = $this->retrieve($sql, $params);
		return new DAOResultFactory($result, $this, '_fromRow', array('id'));
	}
}

?>
