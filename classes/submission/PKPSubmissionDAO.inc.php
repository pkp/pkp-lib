<?php

/**
 * @file classes/submission/PKPSubmissionDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionDAO
 * @ingroup submission
 * @see Submission
 *
 * @brief Operations for retrieving and modifying Submission objects.
 */
use Illuminate\Database\Capsule\Manager as Capsule;

import('lib.pkp.classes.submission.PKPSubmission');
import('lib.pkp.classes.db.SchemaDAO');
import('lib.pkp.classes.services.PKPSchemaService'); // SCHEMA_ constants

define('ORDERBY_DATE_PUBLISHED', 'datePublished');
define('ORDERBY_TITLE', 'title');

abstract class PKPSubmissionDAO extends SchemaDAO {
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
		'dateLastActivity' => 'date_last_activity',
		'dateSubmitted' => 'date_submitted',
		'lastModified' => 'last_modified',
		'locale' => 'locale',
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
	 * @copydoc SchemaDAO::_fromRow()
	 */
	function _fromRow($row) {
		$submission = parent::_fromRow($row);
		$submission->setData('publications', iterator_to_array(
			Services::get('publication')->getMany(['submissionIds' => $submission->getId()])
		));

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

		// Delete publications
		$publicationsIterator = Services::get('publication')->getMany(['submissionIds' => $submissionId]);
		$publicationDao = DAORegistry::getDAO('PublicationDAO'); /* @var $publicationDao PublicationDAO */
		foreach ($publicationsIterator as $publication) {
			$publicationDao->deleteObject($publication);
		}

		// Delete submission files.
		$submissionFilesIterator = Services::get('submissionFile')->getMany([
			'submissionIds' => [$submission->getId()],
		]);
		foreach ($submissionFilesIterator as $submissionFile) {
			Services::get('submissionFile')->delete($submissionFile);
		}

		$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /* @var $reviewRoundDao ReviewRoundDAO */
		$reviewRoundDao->deleteBySubmissionId($submissionId);

		$editDecisionDao = DAORegistry::getDAO('EditDecisionDAO'); /* @var $editDecisionDao EditDecisionDAO */
		$editDecisionDao->deleteDecisionsBySubmissionId($submissionId);

		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /* @var $reviewAssignmentDao ReviewAssignmentDAO */
		$reviewAssignmentDao->deleteBySubmissionId($submissionId);

		// Delete the queries associated with a submission
		$queryDao = DAORegistry::getDAO('QueryDAO'); /* @var $queryDao QueryDAO */
		$queryDao->deleteByAssoc(ASSOC_TYPE_SUBMISSION, $submissionId);

		// Delete the stage assignments.
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
		$stageAssignments = $stageAssignmentDao->getBySubmissionAndStageId($submissionId);
		while ($stageAssignment = $stageAssignments->next()) {
			$stageAssignmentDao->deleteObject($stageAssignment);
		}

		$noteDao = DAORegistry::getDAO('NoteDAO'); /* @var $noteDao NoteDAO */
		$noteDao->deleteByAssoc(ASSOC_TYPE_SUBMISSION, $submissionId);

		$submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO'); /* @var $submissionCommentDao SubmissionCommentDAO */
		$submissionCommentDao->deleteBySubmissionId($submissionId);

		// Delete any outstanding notifications for this submission
		$notificationDao = DAORegistry::getDAO('NotificationDAO'); /* @var $notificationDao NotificationDAO */
		$notificationDao->deleteByAssoc(ASSOC_TYPE_SUBMISSION, $submissionId);

		$submissionEventLogDao = DAORegistry::getDAO('SubmissionEventLogDAO'); /* @var $submissionEventLogDao SubmissionEventLogDAO */
		$submissionEventLogDao->deleteByAssoc(ASSOC_TYPE_SUBMISSION, $submissionId);

		$submissionEmailLogDao = DAORegistry::getDAO('SubmissionEmailLogDAO'); /* @var $submissionEmailLogDao SubmissionEmailLogDAO */
		$submissionEmailLogDao->deleteByAssoc(ASSOC_TYPE_SUBMISSION, $submissionId);

		parent::deleteById($submissionId);
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
		if ($contextId) $params[] = (int) $contextId;

		$result = $this->retrieve(
			'SELECT s.submission_id
				FROM publication_settings ps
				INNER JOIN publications p ON p.publication_id = ps.publication_id
				INNER JOIN submissions s ON p.publication_id = s.current_publication_id
				WHERE ps.setting_name = ? AND ps.setting_value = ?'
				. ($contextId ? ' AND s.context_id = ?' : ''),
			$params
		);
		$row = $result->current();
		return $row ? $this->getById($row->submission_id) : null;
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
		return [
			$this->getSortOption(ORDERBY_TITLE, SORT_DIRECTION_ASC) => __('catalog.sortBy.titleAsc'),
			$this->getSortOption(ORDERBY_TITLE, SORT_DIRECTION_DESC) => __('catalog.sortBy.titleDesc'),
			$this->getSortOption(ORDERBY_DATE_PUBLISHED, SORT_DIRECTION_ASC) => __('catalog.sortBy.datePublishedAsc'),
			$this->getSortOption(ORDERBY_DATE_PUBLISHED, SORT_DIRECTION_DESC) => __('catalog.sortBy.datePublishedDesc'),
		];
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

	/**
	 * Find submission ids by querying settings.
	 * @param $settingName string
	 * @param $settingValue mixed
	 * @param $contextId int
	 * @return array Submission.
	 */
	public function getIdsBySetting($settingName, $settingValue, $contextId) {
		$q = Capsule::table('submissions as s')
			->join('submission_settings as ss', 's.submission_id', '=', 'ss.submission_id')
			->where('ss.setting_name', '=', $settingName)
			->where('ss.setting_value', '=', $settingValue)
			->where('s.context_id', '=', (int) $contextId);

		return $q->select('s.submission_id')
			->pluck('s.submission_id')
			->toArray();
	}
}
