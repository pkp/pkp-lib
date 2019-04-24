<?php

/**
 * SubmissionVersionedDAO short summary.
 *
 * SubmissionVersionedDAO description.
 *
 * @version 1.0
 * @author defstat
 */

import('lib.pkp.classes.submission.ISubmissionVersionedDAO');

class SubmissionVersionedDAO extends DAO {
	function __construct() {
		parent::__construct();
	}

	function getBySubmissionId($submissionId, $submissionVersion = null) {
		$submissionVersion = $this->provideSubmissionVersion($submissionId, $submissionVersion);

		$masterTableName = $this->getMasterTableName();
		$params = array(
			(int) $submissionId,
			(int) $submissionVersion,
		);

		$result = $this->retrieve(
				'SELECT	*
				FROM ' . $masterTableName . '
				WHERE	submission_id = ?
				AND submission_version = ?',
				$params
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Any entity that implements this interface should have a $submissionId attribute.
	 * This method should be able to retrieve the $currentSubmissionVersion from that $submissionId
	 * @param $entity DataObject
	 */
	public function provideSubmissionVersion($submissionId, $submissionVersion = null) {
		if (!$submissionVersion)
			$submissionVersion = $this->getCurrentSubmissionVersion($submissionId, $submissionVersion);

		return $submissionVersion;
	}

	/**
	 * Any entity that implements this interface should have a $submissionId attribute.
	 * This method should be able to retrieve the $currentSubmissionVersion from that $submissionId
	 * @param $entity DataObject
	 * @return int The current submission version
	 */
	public function getCurrentSubmissionVersion($submissionId) {
		$submissionDao = Application::getSubmissionDAO(); /** @var $submissionDao SubmissionDAO */
		$submissionVersion = $submissionDao->getCurrentSubmissionVersionById($submissionId);

		return $submissionVersion;
	}

	function provideSubmissionVersionsForNewVersion($submissionId) {
		$oldVersion = $this->getCurrentSubmissionVersion($submissionId);
		$newVersion = $oldVersion + 1;

		return array($oldVersion, $newVersion);
	}

	function getBySubmission($submission) {
		if (!$submission) return null;

		return $this->getBySubmissionId($submission->getId(), null, $submission->getSubmissionVersion());
	}
}
