<?php

/**
 * @file classes/submission/SubmissionDAO.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionDAO
 * @ingroup submission
 * @see Submission
 *
 * @brief Operations for retrieving and modifying Submission objects.
 */

import('lib.pkp.classes.submission.Submission');
import('lib.pkp.classes.plugins.PKPPubIdPluginDAO');
import('lib.pkp.classes.submission.ISubmissionVersionedDAO');

define('ORDERBY_DATE_PUBLISHED', 'datePublished');
define('ORDERBY_TITLE', 'title');

abstract class SubmissionDAO extends DAO implements PKPPubIdPluginDAO, ISubmissionVersionedDAO {
	var $cache;
	var $authorDao;

	/**
	 * Constructor.
	 */
	function __construct() {
		parent::__construct();
		$this->authorDao = DAORegistry::getDAO('AuthorDAO');
	}

	/**
	 * Callback for a cache miss.
	 * @param $cache Cache
	 * @param $id string
	 * @return Submission
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
		return array_merge(parent::getLocaleFieldNames(), array(
			'title', 'cleanTitle', 'abstract', 'prefix', 'subtitle',
			'discipline', 'subject',
			'coverage',
			'type', 'sponsor', 'source', 'rights',
			'copyrightHolder',
		));
	}

	/**
	 * Get a list of additional fields that do not have
	 * dedicated accessors.
	 * @return array
	 */
	function getAdditionalFieldNames() {
		return array_merge(
			parent::getAdditionalFieldNames(),
			array(
				'pub-id::publisher-id', // FIXME: Move this to a PID plug-in.
				'copyrightYear',
				'licenseURL',
				'datePublished',
			)
		);
	}

	/**
	 * Instantiate a new data object.
	 * @return Submission
	 */
	abstract function newDataObject();

	/**
	 * Internal function to return a Submission object from a row.
	 * @param $row array
	 * @return Submission
	 */
	function _fromRow($row, $params) {
		$submissionVersion = null;
		if (is_array($params)) {
			if (!isset($params['submissionVersion'])) {
				if (isset($params['submissionStatus']) && $params['submissionStatus'] == STATUS_PUBLISHED) {
					$submissionVersion = $this->getCurrentPublishedSubmissionVersion($row['submission_id']);
				} else {
					$submissionVersion = $this->getCurrentSubmissionVersionById($row['submission_id']);
				}
			} else {
				$submissionVersion = $params['submissionVersion'];
			}
		} else {
			$submissionVersion = $params;
		}

		$submission = $this->newDataObject();
		$submission->setId($row['submission_id']);
		$submission->setContextId($row['context_id']);
		$submission->setLocale($row['locale']);
		$submission->setStageId($row['stage_id']);
		$submission->setStatus($row['status']);
		$submission->setSubmissionProgress($row['submission_progress']);
		$submission->setDateSubmitted($this->datetimeFromDB($row['date_submitted']));
		$submission->setDateStatusModified($this->datetimeFromDB($row['date_status_modified']));
		$submission->setLastModified($this->datetimeFromDB($row['last_modified']));
		$submission->setLanguage($row['language']);
		$submission->setCitations($row['citations']);
		$submission->setCurrentSubmissionVersion($row['submission_version']);

		$submission->setSubmissionVersion($submissionVersion);
		$this->getDataObjectSettings('submission_settings', 'submission_id', $submission->getId(), $submission, $submissionVersion);

		return $submission;
	}

	/**
	 * Get all revisions for a submission
	 * @param $submissionId int
	 * @param $contextId int optional
	 * $param $order string optional default: ASC
	 * @return array
	 */
	function getSubmissionVersionsById($submissionId, $order = SORT_DIRECTION_ASC) {
		$params = array((int) $submissionId);

		$sql = 'SELECT DISTINCT ss.submission_version
						FROM 	submission_settings ss
						WHERE 	ss.submission_id = ?
						ORDER BY ss.submission_version ' . $this->getDirectionMapping($order);

		$result = $this->retrieve($sql, $params);

		$submissionVersions = array();
		while(!$result->EOF){
			$row = $result->getRowAssoc(false);
			$submissionVersions[] = $row['submission_version'];
			$result->moveNext();
		}

		$result->close();

		return $submissionVersions;
	}

	function getCurrentPublishedSubmissionVersion($submissionId) {
		$params = array((int) $submissionId);

		$sql = 'SELECT submission_id, MAX(published_submission_version) as submission_version
						FROM published_submissions
						WHERE submission_id = ?
						GROUP BY submission_id';

		$result = $this->retrieve($sql, $params);

		$submissionVersion = null;
		while(!$result->EOF){
			$row = $result->getRowAssoc(false);
			$submissionVersion = $row['submission_version'];
			$result->moveNext();
		}

		$result->close();

		return $submissionVersion;
	}

	function getSubmissionVersionPublicationDate($submissionId, $submissionVersion) {
		$params = array(
			(int) $submissionId,
			(int) $submissionVersion,
			'datePublished',
		);

		$sql = 'SELECT setting_value
						FROM 	submission_settings
						WHERE submission_id = ? AND submission_version = ? AND setting_name = ?';

		$result = $this->retrieve($sql, $params);

		$submissionVersionPublicationDate = null;
		while(!$result->EOF){
			$row = $result->getRowAssoc(false);
			$submissionVersionPublicationDate = $row['setting_value'];
			$result->moveNext();
		}
		$result->close();

		return $submissionVersionPublicationDate;
	}

	/**
	 * Get the latest revision id for a submission
	 * @param $submissionId int
	 * @param $contextId int
	 * @return int
	 */
	function getCurrentSubmissionVersionById($submissionId) {
		if (!$submissionId) 
			return null;

		$submission = $this->getById($submissionId);

		if ($submission)
			return $submission->getCurrentSubmissionVersion();

		return null;
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
		// Delete submission files.
		$submission = $this->getById($submissionId);
		assert(is_a($submission, 'Submission'));
		// 'deleteAllRevisionsBySubmissionId' has to be called before 'rmtree'
		// because SubmissionFileDaoDelegate::deleteObjects checks the file
		// and returns false if the file is not there, which makes the foreach loop in
		// SubmissionFileDAO::_deleteInternally not run till the end.
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
		$submissionFileDao->deleteAllRevisionsBySubmissionId($submissionId);
		import('lib.pkp.classes.file.SubmissionFileManager');
		$submissionFileManager = new SubmissionFileManager($submission->getContextId(), $submission->getId());
		$submissionFileManager->rmtree($submissionFileManager->getBasePath());

		$this->authorDao->deleteBySubmissionId($submissionId);

		$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
		$reviewRoundDao->deleteBySubmissionId($submissionId);

		$editDecisionDao = DAORegistry::getDAO('EditDecisionDAO');
		$editDecisionDao->deleteDecisionsBySubmissionId($submissionId);

		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		$reviewAssignmentDao->deleteBySubmissionId($submissionId);

		// Delete the queries associated with a submission
		$queryDao = DAORegistry::getDAO('QueryDAO');
		$queryDao->deleteByAssoc(ASSOC_TYPE_SUBMISSION, $submissionId);

		// Delete the stage assignments.
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
		$stageAssignments = $stageAssignmentDao->getBySubmissionAndStageId($submissionId);
		while ($stageAssignment = $stageAssignments->next()) {
			$stageAssignmentDao->deleteObject($stageAssignment);
		}

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
	 * @copydoc PKPPubIdPluginDAO::pubIdExists()
	 */
	function pubIdExists($pubIdType, $pubId, $excludePubObjectId, $contextId) {
		$result = $this->retrieve(
			'SELECT COUNT(*)
			FROM submission_settings sst
				INNER JOIN submissions s ON sst.submission_id = s.submission_id
			WHERE sst.setting_name = ? and sst.setting_value = ? and sst.submission_id <> ? AND s.context_id = ?',
			array(
				'pub-id::'.$pubIdType,
				$pubId,
				(int) $excludePubObjectId,
				(int) $contextId
			)
		);
		$returner = $result->fields[0] ? true : false;
		$result->Close();
		return $returner;
	}

	/**
	 * Stamp a submission status modification date.
	 * This updates both the supplied Submission object and the database.
	 * @param $submission Submission
	 */
	public function stampStatusModified($submission) {
		// Stamp the active submission object
		$submission->stampStatusModified();

		// Stamp the database entry
		$this->update(
			sprintf(
				'UPDATE submissions SET date_status_modified = %s WHERE submission_id = ?',
				$this->datetimeToDB($submission->getDateStatusModified())
			),
			array((int) $submission->getId())
		);

		$this->stampModified($submission);
	}

	/**
	 * Stamp a submission modification date.
	 * This updates both the supplied Submission object and the database.
	 * @param $submission Submission
	 */
	public function stampModified($submission) {
		// Stamp the active submission object
		$submission->stampModified();

		// Stamp the database entry
		$this->update(
			sprintf(
				'UPDATE submissions SET last_modified = %s WHERE submission_id = ?',
				$this->datetimeToDB($submission->getLastModified())
			),
			array((int) $submission->getId())
		);
	}

	/**
	 * @copydoc PKPPubIdPluginDAO::changePubId()
	 */
	function changePubId($pubObjectId, $pubIdType, $pubId) {
		$idFields = array(
			'submission_id', 'submission_version', 'locale', 'setting_name'
		);
		$updateArray = array(
			'submission_id' => (int) $pubObjectId,
			'submission_version' => $this->getCurrentSubmissionVersionById($submissionId),
			'locale' => '',
			'setting_name' => 'pub-id::'.$pubIdType,
			'setting_type' => 'string',
			'setting_value' => (string)$pubId
		);
		$this->replace('submission_settings', $updateArray, $idFields);
		$this->flushCache();
	}

	/**
	 * @copydoc PKPPubIdPluginDAO::deletePubId()
	 */
	function deletePubId($pubObjectId, $pubIdType) {
		$settingName = 'pub-id::'.$pubIdType;
		$this->update(
			'DELETE FROM submission_settings WHERE setting_name = ? AND submission_id = ?',
			array(
				$settingName,
				(int)$pubObjectId
			)
		);
		$this->flushCache();
	}

	/**
	 * @copydoc PKPPubIdPluginDAO::deleteAllPubIds()
	 */
	function deleteAllPubIds($contextId, $pubIdType) {
		$settingName = 'pub-id::'.$pubIdType;

		$submissions = $this->getByContextId($contextId);
		while ($submission = $submissions->next()) {
			$this->update(
				'DELETE FROM submission_settings WHERE setting_name = ? AND submission_id = ?',
				array(
					$settingName,
					(int)$submission->getId()
				)
			);
		}
		$this->flushCache();
	}

	/**
	 * Update the settings for this object
	 * @param $submission object
	 */
	function updateLocaleFields($submission) {
		$this->updateDataObjectSettings('submission_settings', $submission, array(
			'submission_id' => $submission->getId(),
			'submission_version' => ($submission->getSubmissionVersion())
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
	 * @param $submissionVersion int optional
	 * @return Submission
	 */
	function getById($submissionId, $contextId = null, $useCache = false, $submissionVersion = null) {
		if ($useCache) {
			$cache = $this->_getCache();
			$submission = $cache->get($submissionId);
			if ($submission && (!$contextId || $contextId == $submission->getContextId())) {
				return $submission;
			}
			unset($submission);
		}

		$params = $this->getFetchParameters();
		$params[] = (int) $submissionId;
		if ($contextId) $params[] = (int) $contextId;

		$result = $this->retrieve(
			'SELECT	s.*,
				' . $this->getFetchColumns() . '
			FROM	submissions s
				LEFT JOIN published_submissions ps ON (s.submission_id = ps.submission_id) and (ps.published_submission_version = s.submission_version) and ps.is_current_submission_version = 1
				' . $this->getFetchJoins() . '
			WHERE	s.submission_id = ?
				' . ($contextId?' AND s.context_id = ?':''),
			$params
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner = $this->_fromRow($result->GetRowAssoc(false), $submissionVersion);
		}

		$result->Close();
		return $returner;
	}

	/**
	 * Retrieve a submission by ID only if the submission is not published, has been submitted, and does not
	 * belong to the user in question and is not STATUS_DECLINED.
	 * @param int $submissionId
	 * @param int $userId
	 * @param int $contextId
	 * @param boolean $useCache
	 * @return Submission
	 */
	function getAssignedById($submissionId, $userId, $contextId = null, $useCache = false) {
		if ($useCache) {
			$cache = $this->_getCache();
			$submission = $cache->get($submissionId);
			if ($submission && (!$contextId || $contextId == $submission->getContextId())) {
				return $submission;
			}
			unset($submission);
		}

		$params = array_merge(
			array((int) ROLE_ID_AUTHOR),
			$this->_getFetchParameters(),
			array((int) $submissionId)
		);
		if ($contextId) $params[] = (int) $contextId;

		$result = $this->retrieve(
			'SELECT	s.*,
				' . $this->getFetchColumns() . '
			FROM	submissions s
				LEFT JOIN published_submissions ps ON (s.submission_id = ps.submission_id) and (ps.published_submission_version = s.submission_version) and ps.is_current_submission_version = 1
				' . $this->getCompletionJoins() . '
				LEFT JOIN stage_assignments asa ON (asa.submission_id = s.submission_id)
				LEFT JOIN user_groups aug ON (asa.user_group_id = aug.user_group_id AND aug.role_id = ?)
				' . $this->_getFetchJoins() . '
			WHERE	s.submission_id = ?
				' . $this->getCompletionConditions(false) . ' AND
				AND aug.user_group_id IS NULL
				AND s.date_submitted IS NOT NULL
				AND s.status <> ' .  STATUS_DECLINED .
				($contextId?' AND s.context_id = ?':'')
			. ' ORDER BY s.submission_id',
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
		$params = $this->getFetchParameters();
		$params[] = (int) $contextId;

		$result = $this->retrieve(
			'SELECT	s.*,
				' . $this->getFetchColumns() . '
			FROM	submissions s
				LEFT JOIN published_submissions ps ON (s.submission_id = ps.submission_id) and (ps.published_submission_version = s.submission_version) and ps.is_current_submission_version = 1
				' . $this->getFetchJoins() . '
			WHERE s.context_id = ?
			ORDER BY s.submission_id',
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
		$params = array_merge(
			$this->_getFetchParameters(),
			array((int) ROLE_ID_AUTHOR, (int) $userId)
		);
		if ($contextId) $params[] = (int) $contextId;

		$result = $this->retrieve(
			'SELECT	s.*,
				' . $this->getFetchColumns() . '
			FROM	submissions s
				LEFT JOIN published_submissions ps ON (s.submission_id = ps.submission_id) and (ps.published_submission_version = s.submission_version) and ps.is_current_submission_version = 1
				' . $this->getFetchJoins() . '
			WHERE 	s.submission_id IN (SELECT asa.submission_id FROM stage_assignments asa, user_groups aug WHERE asa.user_group_id = aug.user_group_id AND aug.role_id = ? AND asa.user_id = ?)' .
				($contextId?' AND s.context_id = ?':'') .
			' ORDER BY s.submission_id',
			$params
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/*
	 * Delete all submissions by context ID.
	 * @param $contextId int
	 */
	function deleteByContextId($contextId) {
		$submissions = $this->getByContextId($contextId);
		while ($submission = $submissions->next()) {
			$this->deleteById($submission->getId());
		}
	}

	/**
	 * Reset the attached licenses of all submissions in a context to context defaults.
	 * @param $contextId int
	 */
	function resetPermissions($contextId) {
		$submissions = $this->getByContextId($contextId);
		while ($submission = $submissions->next()) {
			$submission->setCopyrightYear($submission->_getContextLicenseFieldValue(null, PERMISSIONS_FIELD_COPYRIGHT_YEAR));
			$submission->setCopyrightHolder($submission->_getContextLicenseFieldValue(null, PERMISSIONS_FIELD_COPYRIGHT_HOLDER), null);
			$submission->setLicenseURL($submission->_getContextLicenseFieldValue(null, PERMISSIONS_FIELD_LICENSE_URL));
			$this->updateObject($submission);
		}
		$this->flushCache();
	}

	/**
	 * Helper function to collect the SQL WHERE statement for searching
	 * submissions by word(s)
	 *
	 * @param $phrase string The search phrase. One or more words.
	 * @return string
	 */
	public function getSearchWhere($phrase) {
		$searchWhere = '';
		if ($phrase) {
			$words = explode(" ", trim($phrase));
			if (count($words)) {
				$searchWhere = ' AND (';
				$searchClauses = array();
				foreach($words as $word) {
					$clause = '(';
					$clause .= '(ss.setting_name = ? AND ss.setting_value LIKE ?)';
					$params[] = 'title';
					$params[] = '%' . $word . '%';
					$clause .= ' OR (asgs.setting_value LIKE ? OR asfs.setting_value LIKE ?)';
					$params[] = '%' . $word . '%';
					$params[] = '%' . $word . '%';
					$searchClauses[] = $clause . ')';
				}
				$searchWhere .= join(' AND ', $searchClauses) . ')';
			}
		}

		return $searchWhere;
	}

	/**
	 * Associate a category with a submission.
	 * @param $submissionId int
	 * @param $categoryId int
	 */
	function addCategory($submissionId, $categoryId) {
		$this->update(
			'INSERT INTO submission_categories
				(submission_id, category_id)
			VALUES
				(?, ?)',
			array(
				(int) $submissionId,
				(int) $categoryId
			)
		);
	}

	/**
	 * Unassociate a category with a submission.
	 * @param $submissionId int
	 * @param $categoryId int
	 */
	function removeCategory($submissionId, $categoryId) {
		$this->update(
			'DELETE FROM submission_categories WHERE submission_id = ? AND category_id = ?',
			array(
				(int) $submissionId,
				(int) $categoryId
			)
		);
	}

	/**
	 * Unassociate all categories.
	 * @param $submissionId int
	 */
	function removeCategories($submissionId) {
		$this->update(
			'DELETE FROM submission_categories WHERE submission_id = ?',
			(int) $submissionId
		);
	}

	/**
	 * Get the categories associated with a given submission.
	 * @param $submissionId int The submission id.
	 * @param $contextId int (optional) The submission's context id.
	 * @return DAOResultFactory
	 */
	function getCategories($submissionId, $contextId = null) {
		$params = array((int) $submissionId);
		if ($contextId) $params[] = (int) $contextId;

		$categoryDao = DAORegistry::getDAO('CategoryDAO');
		$result = $this->retrieve(
			'SELECT	c.*
			FROM	categories c,
				submission_categories sc,
				submissions s
			WHERE	c.category_id = sc.category_id AND
				s.submission_id = ? AND
			' . ($contextId?' c.context_id = s.context_id AND s.context_id = ? AND':'') . '
				s.submission_id = sc.submission_id',
			$params
		);

		// Delegate category creation to the category DAO.
		return new DAOResultFactory($result, $categoryDao, '_fromRow');
	}

	/**
	 * Get the categories not associated with a given submission.
	 * @param $submissionId int
	 * @return DAOResultFactory
	 */
	function getUnassignedCategories($submissionId, $contextId = null) {
		$params = array((int) $submissionId);
		if ($contextId) $params[] = (int) $contextId;

		$categoryDao = DAORegistry::getDAO('CategoryDAO');
		// The strange ORDER BY clause is to return subcategories
		// immediately after their parent category's entry.
		$result = $this->retrieve(
			'SELECT	c.*
			FROM	submissions s
				JOIN categories c ON (c.context_id = s.context_id)
				LEFT JOIN submission_categories sc ON (s.submission_id = sc.submission_id AND sc.category_id = c.category_id)
			WHERE	s.submission_id = ? AND
				' . ($contextId?' s.context_id = ? AND':'') . '
				sc.submission_id IS NULL
			ORDER BY CASE WHEN c.parent_id = 0 THEN c.category_id * 2 ELSE (c.parent_id * 2) + 1 END ASC',
			$params
		);

		// Delegate category creation to the category DAO.
		return new DAOResultFactory($result, $categoryDao, '_fromRow');
	}

	/**
	 * Check if a submission exists in a category with the specified ID.
	 * @param $submissionId int
	 * @param $categoryId int
	 * @return boolean
	 */
	function categoryAssociationExists($submissionId, $categoryId) {
		$result = $this->retrieve(
			'SELECT COUNT(*) FROM submission_categories WHERE submission_id = ? AND category_id = ?',
			array((int) $submissionId, (int) $categoryId)
		);
		$returner = isset($result->fields[0]) && $result->fields[0] == 1 ? true : false;

		$result->Close();
		return $returner;
	}


	//
	// Protected functions
	//
	/**
	 * Return a list of extra parameters to bind to the submission fetch queries.
	 * @return array
	 */
	abstract protected function getFetchParameters();

	/**
	 * Return a SQL snippet of extra columns to fetch during submission fetch queries.
	 * @return string
	 */
	abstract protected function getFetchColumns();

	/**
	 * Return a SQL snippet of columns to group by the submission fetch queries.
	 * See bug #8557, all tables that have columns selected must have one column listed here
	 * to keep PostgreSQL happy.
	 * @return string
	 */
	abstract protected function getGroupByColumns();

	/**
	 * Return a SQL snippet of extra joins to include during fetch queries.
	 * @return string
	 */
	abstract protected function getFetchJoins();

	/**
	 * Return a SQL snippet of extra sub editor related join to include during fetch queries.
	 * @return string
	 */
	abstract protected function getSubEditorJoin();

	/**
	 * Sanity test to cast values to int for database queries.
	 * @param string $value
	 * @return int
	 */
	protected function _arrayWalkIntCast($value) {
		return (int) $value;
	}

	/**
	 * Get additional joins required to establish whether the submission is "completed".
	 * @return string
	 */
	protected function getCompletionJoins() {
		return '';
	}

	/**
	 * Get conditions required to establish whether the submission is "completed".
	 * @param $completed boolean True for completed submissions; false for incomplete
	 * @return string
	 */
	abstract protected function getCompletionConditions($completed);

	/**
	 * Get default sort option.
	 * @return string
	 */
	function getDefaultSortOption() {
		return $this->getSortOption(ORDERBY_DATE_PUBLISHED, SORT_DIRECTION_DESC);
	}

	/**
	 * Map a column heading value to a database value for sorting
	 * @param $sortBy string
	 * @return string
	 */
	function getSortMapping($sortBy) {
		switch ($sortBy) {
			case ORDERBY_TITLE:
				return 'st.setting_value';
			case ORDERBY_DATE_PUBLISHED:
				return 'ps.date_published';
			default: return null;
		}
	}

	/**
	 * Get possible sort options.
	 * @return array
	 */
	function getSortSelectOptions() {
		return array(
			$this->getSortOption(ORDERBY_TITLE, SORT_DIRECTION_ASC) => __('catalog.sortBy.titleAsc'),
			$this->getSortOption(ORDERBY_TITLE, SORT_DIRECTION_DESC) => __('catalog.sortBy.titleDesc'),
			$this->getSortOption(ORDERBY_DATE_PUBLISHED, SORT_DIRECTION_ASC) => __('catalog.sortBy.datePublishedAsc'),
			$this->getSortOption(ORDERBY_DATE_PUBLISHED, SORT_DIRECTION_DESC) => __('catalog.sortBy.datePublishedDesc'),
		);
	}

	/**
	 * Get sort option.
	 * @param $sortBy string
	 * @param $sortDir int
	 * @return string
	 */
	function getSortOption($sortBy, $sortDir) {
		return $sortBy .'-' . $sortDir;
	}

	/**
	 * Get sort way for a sort option.
	 * @param $sortOption string concat(sortBy, '-', sortDir)
	 * @return string
	 */
	function getSortBy($sortOption) {
		list($sortBy, $sortDir) = explode("-", $sortOption);
		return $sortBy;
	}

	/**
	 * Get sort direction for a sort option.
	 * @param $sortOption string concat(sortBy, '-', sortDir)
	 * @return int
	 */
	function getSortDirection($sortOption) {
		list($sortBy, $sortDir) = explode("-", $sortOption);
		return $sortDir;
	}

	#region ISubmissionVersionedDAO Members

	/**
	 *
	 * @param  $submissionId
	 * @param  $submissionVersion
	 */
	function getBySubmissionId($submissionId, $submissionVersion = null) {
		// TODO: implement the function ISubmissionVersionedDAO::getBySubmissionId
	}

	/**
	 *
	 * @param  $submissionId
	 */
	function newVersion($submissionId) {
		$oldVersion = $this->getCurrentSubmissionVersionById($submissionId);
		$newVersion = $oldVersion + 1;
		$submission = $this->getById($submissionId, null, false, $oldVersion);

		$submission->setData('submissionVersion', $newVersion);
		$submission->setDatePublished(null);

		foreach ($this->versioningRelatedEntityDaos() as $versioningRelatedEntityDaoName) {
			$versioningRelatedEntityDao = DAORegistry::getDAO($versioningRelatedEntityDaoName);
			if (is_a($versioningRelatedEntityDao, 'ISubmissionVersionedDAO')) {
				/** @var $versioningRelatedEntityDao ISubmissionVersionedDAO */
				$versioningRelatedEntityDao->newVersion($submissionId);
			}
		}

		$submission->setCurrentSubmissionVersion($newVersion);
		$submission->setSubmissionVersion($newVersion);
		$this->updateObject($submission);
	}

	/**
	 */
	function getVersioningAssocType() {
		// TODO: implement the function ISubmissionVersionedDAO::getVersioningAssocType
	}

	#endregion

	function versioningRelatedEntityDaos() {
		return array('AuthorDAO', 'SubmissionFileDAO');
	}

	#region ISubmissionVersionedDAO Members

	function getMasterTableName() {
		return;
	}

	#endregion
}
