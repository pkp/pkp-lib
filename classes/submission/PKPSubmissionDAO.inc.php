<?php

/**
 * @file classes/submission/PKPSubmissionDAO.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionDAO
 * @ingroup submission
 * @see Submission
 *
 * @brief Operations for retrieving and modifying Submission objects.
 */

import('lib.pkp.classes.submission.PKPSubmission');
import('lib.pkp.classes.db.SchemaDAO');
import('lib.pkp.classes.plugins.PKPPubIdPluginDAO');

define('ORDERBY_DATE_PUBLISHED', 'datePublished');
define('ORDERBY_TITLE', 'title');

abstract class PKPSubmissionDAO extends SchemaDAO implements PKPPubIdPluginDAO {
	var $cache;
	var $authorDao;

	/** @copydoc SchemaDAO::$schemaName */
	public $schemaName = SCHEMA_SUBMISSION;

	/** @copydoc SchemaDAO::$tableName */
	public $tableName = 'submissions';

	/** @copydoc SchemaDAO::$settingsTableName */
	public $settingsTableName = 'submission_settings';

	/** @copydoc SchemaDAO::$primaryKeyColumn */
	public $primaryKeyColumn = 'submission_id';

	/** @copydoc SchemaDAO::$primaryTableColumns */
	public $primaryTableColumns = [
		'id' => 'submission_id',
		'contextId' => 'context_id',
		'currentPublicationId' => 'current_publication_id',
		'dateStatusModified' => 'date_status_modified',
		'dateSubmitted' => 'date_submitted',
		'lastModified' => 'last_modified',
		'stageId' => 'stage_id',
		'status' => 'status',
		'submissionProgress' => 'submission_progress',
	];

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
	 * Instantiate a new data object.
	 * @return Submission
	 */
	abstract function newDataObject();

	/**
	 * @copydoc SchemaDAO::_fromRow()
	 */
	function _fromRow($row) {
		$submission = parent::_fromRow($row);
		$submission->setData('publications', Services::get('publication')->getMany(['submissionIds' => $submission->getId()]));

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
		$submission = $this->getById($submissionId);
		if (!is_a($submission, 'Submission')) {
			throw new Exception('Could not delete submission. No submission with the id ' . (int) $submissionId . ' was found.');
		}

		parent::deleteById($submissionId);

		// Delete publications
		$publications = Services::get('publication')->getMany(['submissionIds' => $submissionId]);
		foreach ($publications as $publication) {
			DAORegistry::getDAO('PublicationDAO')->deleteObject($publication);
		}

		// Delete submission files.
		// 'deleteAllRevisionsBySubmissionId' has to be called before 'rmtree'
		// because SubmissionFileDaoDelegate::deleteObjects checks the file
		// and returns false if the file is not there, which makes the foreach loop in
		// SubmissionFileDAO::_deleteInternally not run till the end.
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
		$submissionFileDao->deleteAllRevisionsBySubmissionId($submissionId);
		import('lib.pkp.classes.file.SubmissionFileManager');
		$submissionFileManager = new SubmissionFileManager($submission->getContextId(), $submission->getId());
		$submissionFileManager->rmtree($submissionFileManager->getBasePath());

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
	 * @copydoc PKPPubIdPluginDAO::changePubId()
	 */
	function changePubId($pubObjectId, $pubIdType, $pubId) {
		$idFields = array(
			'submission_id', 'locale', 'setting_name'
		);
		$updateArray = array(
			'submission_id' => (int) $pubObjectId,
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
	 * Retrieve submission by public id
	 * @param $pubIdType string One of the NLM pub-id-type values or
	 * 'other::something' if not part of the official NLM list
	 * (see <http://dtd.nlm.nih.gov/publishing/tag-library/n-4zh0.html>).
	 * @param $pubId string
	 * @param $contextId int
	 * @return Submission|null
	 */
	function getByPubId($pubIdType, $pubId, $contextId = null) {

		$params = [
			'pub-id::' . $pubIdType,
			$pubId,
		];
		if ($contextId) {
			$params[] = $contextId;
		}

		$result = $this->retrieve(
			'SELECT s.submission_id
				FROM publication_settings ps
				INNER JOIN publications p ON p.publication_id = ps.publication_id
				INNER JOIN submissions s ON p.publication_id = s.current_publication_id
				WHERE ps.setting_name = ? AND ps.setting_value = ?'
				. ($contextId ? ' AND s.context_id = ?' : ''),
			$params
		);

		$submissionId = $result->fields[0];

		if (!$submissionId) {
			return null;
		}

		return $this->getById($submissionId);
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
		$cache = $this->_getCache();
		$cache->flush();
	}

	/**
	 * Get all submissions for a context.
	 * @param $contextId int
	 * @return DAOResultFactory containing matching Submissions
	 */
	function getByContextId($contextId) {
		$result = $this->retrieve(
			'SELECT * FROM ' . $this->tableName . ' WHERE context_id = ?',
			[(int) $contextId]
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
			$publications = (array) $submission->getData('publications');
			if (empty($publications)) {
				continue;
			}
			$params = [
				'copyrightYear' => $submission->_getContextLicenseFieldValue(null, PERMISSIONS_FIELD_COPYRIGHT_YEAR),
				'copyrightHolder' => $submission->_getContextLicenseFieldValue(null, PERMISSIONS_FIELD_COPYRIGHT_HOLDER),
				'licenseUrl' => $submission->_getContextLicenseFieldValue(null, PERMISSIONS_FIELD_LICENSE_URL),
			];
			foreach ($publications as $publication) {
				$publication = Services::get('publication')->edit($publication, $params, Application::get()->getRequest());
			}
		}
		$this->flushCache();
	}

	/**
	 * Get default sort option.
	 * @return string
	 */
	function getDefaultSortOption() {
		return $this->getSortOption(ORDERBY_DATE_PUBLISHED, SORT_DIRECTION_DESC);
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
}
