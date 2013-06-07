<?php

/**
 * @file classes/submission/SubmissionDAO.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionDAO
 * @ingroup submission
 * @see Submission
 *
 * @brief Operations for retrieving and modifying Submission objects.
 */

import('lib.pkp.classes.submission.Submission');

class SubmissionDAO extends DAO {
	var $cache;
	var $authorDao;

	/**
	 * Constructor.
	 */
	function SubmissionDAO() {
		parent::DAO();
		$this->authorDao = DAORegistry::getDAO('AuthorDAO');
	}

	/**
	 * Callback for a cache miss.
	 * @param $cache Cache
	 * @param $id string
	 * @return Monograph
	 */
	function _cacheMiss($cache, $id) {
		$submission = $this->getById($id, null, false);
		$cache->setCache($id, $submission);
		return $submission;
	}

	/**
	 * Get the submission cache.
	 * @return Cache
	 */
	function _getCache() {
		if (!isset($this->cache)) {
			$cacheManager = CacheManager::getManager();
			$this->cache = $cacheManager->getObjectCache('submissions', 0, array(&$this, '_cacheMiss'));
		}
		return $this->cache;
	}

	/**
	 * Get a list of fields for which localized data is supported
	 * @return array
	 */
	function getLocaleFieldNames() {
		return array(
			'title', 'cleanTitle', 'abstract', 'prefix', 'subtitle',
			'discipline', 'subjectClass', 'subject',
			'coverageGeo', 'coverageChron', 'coverageSample',
			'type', 'sponsor', 'source', 'rights'
		);
	}

	/**
	 * Get a list of additional fields that do not have
	 * dedicated accessors.
	 * @return array
	 */
	function getAdditionalFieldNames() {
		$additionalFields = parent::getAdditionalFieldNames();
		// FIXME: Move this to a PID plug-in.
		$additionalFields[] = 'pub-id::publisher-id';
		return $additionalFields;
	}

	/**
	 * Instantiate a new data object.
	 * @return Submission
	 */
	function newDataObject() {
		return new Submission();
	}

	/**
	 * Internal function to return a Submission object from a row.
	 * @param $row array
	 * @return Submission
	 */
	function _fromRow($row) {
		$submission = $this->newDataObject();

		$submission->setId($row['submission_id']);
		$submission->setContextId($row['context_id']);
		$submission->setLocale($row['locale']);
		$submission->setUserId($row['user_id']);
		$submission->setStageId($row['stage_id']);
		$submission->setStatus($row['status']);
		$submission->setSubmissionProgress($row['submission_progress']);
		$submission->setDateSubmitted($this->datetimeFromDB($row['date_submitted']));
		$submission->setDateStatusModified($this->datetimeFromDB($row['date_status_modified']));
		$submission->setDatePublished($this->datetimeFromDB($row['date_published']));
		$submission->setLastModified($this->datetimeFromDB($row['last_modified']));
		$submission->setLanguage($row['language']);
		$submission->setCommentsToEditor($row['comments_to_ed']);

		$this->getDataObjectSettings('submission_settings', 'submission_id', $submission->getId(), $submission);

		return $submission;
	}

	/**
	 * Delete a submission.
	 * @param $submission Submission
	 */
	function deleteObject($submission) {
		return $this->deleteById($submission->getId());
	}

	/**
	 * Delete a submission by ID.
	 * @param $submissionId int
	 */
	function deleteById($submissionId) {
		$this->authorDao->deleteAuthorsBySubmission($submissionId);

		$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
		$reviewRoundDao->deleteBySubmissionId($submissionId);

		$editDecisionDao = DAORegistry::getDAO('EditDecisionDAO');
		$editDecisionDao->deleteDecisionsBySubmissionId($submissionId);

		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		$reviewAssignmentDao->deleteBySubmissionId($submissionId);

		// Signoff DAOs
		$signoffDao = DAORegistry::getDAO('SignoffDAO');
		$submissionFileSignoffDao = DAORegistry::getDAO('SubmissionFileSignoffDAO');

		// Delete Signoffs associated with a submission file of this submission.
		$submissionFileSignoffs = $submissionFileSignoffDao->getAllBySubmission($submissionId);
		while ($signoff = $submissionFileSignoffs->next()) {
			$signoffDao->deleteObject($signoff);
		}

		// Delete the Signoffs associated with the submission itself.
		$submissionSignoffs = $signoffDao->getAllByAssocType(ASSOC_TYPE_SUBMISSION, $submissionId);
		while ($signoff = $submissionSignoffs->next()) {
			$signoffDao->deleteObject($signoff);
		}

		// Delete the stage assignments.
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
		$stageAssignments = $stageAssignmentDao->getBySubmissionAndStageId($submissionId);
		while ($stageAssignment = $stageAssignments->next()) {
			$stageAssignmentDao->deleteObject($stageAssignment);
		}

		// N.B. Files must be deleted after signoffs to identify submission file signoffs.
		// Delete submission files.
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
		$submissionFileDao->deleteAllRevisionsBySubmissionId($submissionId);

		$commentDao = DAORegistry::getDAO('CommentDAO');
		$commentDao->deleteBySubmissionId($submissionId);

		$noteDao = DAORegistry::getDAO('NoteDAO');
		$noteDao->deleteByAssoc(ASSOC_TYPE_SUBMISSION, $submissionId);

		$submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO');
		$submissionCommentDao->deleteBySubmissionId($submissionId);

		// Delete any outstanding notifications for this submission
		$notificationDao = DAORegistry::getDAO('NotificationDAO');
		$notificationDao->deleteByAssoc(ASSOC_TYPE_SUBMISSION, $submissionId);

		$submissionEventLogDao = DAORegistry::getDAO('SubmissionEventLogDAO');
		$submissionEventLogDao->deleteByAssoc(ASSOC_TYPE_SUBMISSION, $submissionId);

		$submissionEmailLogDao = DAORegistry::getDAO('SubmissionEmailLogDAO');
		$submissionEmailLogDao->deleteByAssoc(ASSOC_TYPE_SUBMISSION, $submissionId);

		// Delete controlled vocab lists assigned to this submission
		$submissionKeywordDao = DAORegistry::getDAO('SubmissionKeywordDAO');
		$submissionKeywordVocab = $submissionKeywordDao->getBySymbolic(CONTROLLED_VOCAB_SUBMISSION_KEYWORD, ASSOC_TYPE_SUBMISSION, $submissionId);
		if (isset($submissionKeywordVocab)) {
			$submissionKeywordDao->deleteObject($submissionKeywordVocab);
		}

		$submissionDisciplineDao = DAORegistry::getDAO('SubmissionDisciplineDAO');
		$submissionDisciplineVocab = $submissionDisciplineDao->getBySymbolic(CONTROLLED_VOCAB_SUBMISSION_DISCIPLINE, ASSOC_TYPE_SUBMISSION, $submissionId);
		if (isset($submissionDisciplineVocab)) {
			$submissionDisciplineDao->deleteObject($submissionDisciplineVocab);
		}

		$submissionAgencyDao = DAORegistry::getDAO('SubmissionAgencyDAO');
		$submissionAgencyVocab = $submissionAgencyDao->getBySymbolic(CONTROLLED_VOCAB_SUBMISSION_AGENCY, ASSOC_TYPE_SUBMISSION, $submissionId);
		if (isset($submissionAgencyVocab)) {
			$submissionAgencyDao->deleteObject($submissionAgencyVocab);
		}

		$submissionLanguageDao = DAORegistry::getDAO('SubmissionLanguageDAO');
		$submissionLanguageVocab = $submissionLanguageDao->getBySymbolic(CONTROLLED_VOCAB_SUBMISSION_LANGUAGE, ASSOC_TYPE_SUBMISSION, $submissionId);
		if (isset($submissionLanguageVocab)) {
			$submissionLanguageDao->deleteObject($submissionLanguageVocab);
		}

		$submissionSubjectDao = DAORegistry::getDAO('SubmissionSubjectDAO');
		$submissionSubjectVocab = $submissionSubjectDao->getBySymbolic(CONTROLLED_VOCAB_SUBMISSION_SUBJECT, ASSOC_TYPE_SUBMISSION, $submissionId);
		if (isset($submissionSubjectVocab)) {
			$submissionSubjectDao->deleteObject($submissionSubjectVocab);
		}

		$this->update('DELETE FROM submission_settings WHERE submission_id = ?', (int) $submissionId);
		$this->update('DELETE FROM submissions WHERE submission_id = ?', (int) $submissionId);
	}

	/**
	 * Change the public ID of a submission.
	 * @param $submissionId int
	 * @param $pubIdType string One of the NLM pub-id-type values or
	 * 'other::something' if not part of the official NLM list
	 * (see <http://dtd.nlm.nih.gov/publishing/tag-library/n-4zh0.html>).
	 * @param $pubId string
	 */
	function changePubId($submissionId, $pubIdType, $pubId) {
		$this->updateSetting($submissionId, 'pub-id::'.$pubIdType, $pubId, 'string');
	}

	/**
	 * Update the settings for this object
	 * @param $submission object
	 */
	function updateLocaleFields($submission) {
		$this->updateDataObjectSettings('submission_settings', $submission, array(
			'submission_id' => $submission->getId()
		));
	}

	/**
	 * Get the ID of the last inserted submission.
	 * @return int
	 */
	function getInsertId() {
		return $this->_getInsertId('submissions', 'submission_id');
	}

	/**
	 * Flush the submission cache.
	 */
	function flushCache() {
		// Because both published_submissions and submissions are
		// cached by submission ID, flush both caches on update.
		$cache = $this->_getCache();
		$cache->flush();
	}

	/**
	 * Retrieve a submission by ID.
	 * @param $submissionId int
	 * @param $contextId int optional
	 * @param $useCache boolean optional
	 * @return Submission
	 */
	function getById($submissionId, $contextId = null, $useCache = false) {
		if ($useCache) {
			$cache = $this->_getCache();
			$submission = $cache->get($submissionId);
			if ($submission && (!$contextId || $contextId == $submission->getContextId())) {
				return $submission;
			}
			unset($submission);
		}

		$params = $this->_getFetchParameters();
		$params[] = (int) $submissionId;
		if ($contextId) $params[] = (int) $contextId;

		$result = $this->retrieve(
			'SELECT	s.*, ps.date_published,
				' . $this->_getFetchColumns() . '
			FROM	submissions s
				LEFT JOIN published_submissions ps ON (s.submission_id = ps.submission_id)
				' . $this->_getFetchJoins() . '
			WHERE	s.submission_id = ?
				' . ($contextId?' AND s.context_id = ?':''),
			$params
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner = $this->_fromRow($result->GetRowAssoc(false));
		}

		$result->Close();
		return $returner;
	}

	/**
	 * Get all submissions for a context.
	 * @param $contextId int
	 * @return DAOResultFactory containing matching Submissions
	 */
	function getByContextId($contextId) {
		$params = $this->_getFetchParameters();
		$params[] = (int) $contextId;

		$result = $this->retrieve(
			'SELECT	s.*, ps.date_published,
				' . $this->_getFetchColumns() . '
			FROM	submissions s
				LEFT JOIN published_submissions ps ON (s.submission_id = ps.submission_id)
				' . $this->_getFetchJoins() . '
			WHERE	s.context_id = ?',
			$params
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Get all submissions for a user.
	 * @param $userId int
	 * @param $contextId int optional
	 * @return array Submissions
	 */
	function getByUserId($userId, $contextId = null) {
		$params = $this->_getFetchParameters();
		$params[] = (int) $userId;
		if ($contextId) $params[] = (int) $contextId;

		$result = $this->retrieve(
			'SELECT	s.*, ps.date_published,
				' . $this->_getFetchColumns() . '
			FROM	submissions s
				LEFT JOIN published_submissions ps ON (s.submission_id = ps.submission_id)
				' . $this->_getFetchJoins() . '
			WHERE	s.user_id = ?' .
				($contextId?' AND s.context_id = ?':''),
			$params
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}


	//
	// Protected functions
	//
	/**
	 * Return a list of extra parameters to bind to the submission fetch queries.
	 * @return array
	 */
	protected function _getFetchParameters() {
		assert(false); // To be overridden by subclasses
	}

	/**
	 * Return a SQL snippet of extra columns to fetch during submission fetch queries.
	 * @return string
	 */
	protected function _getFetchColumns() {
		assert(false); // To be overridden by subclasses
	}

	/**
	 * Return a SQL snippet of extra joins to include during fetch queries.
	 * @return string
	 */
	protected function _getFetchJoins() {
		assert(false); // To be overridden by subclasses
	}
}

?>
