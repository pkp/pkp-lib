<?php

/**
 * @file classes/submission/PKPSubmissionFileDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionFileDAO
 * @ingroup submission
 * @see SubmissionFile
 *
 * @brief Operations for retrieving and modifying submission files
 */
use Illuminate\Database\Capsule\Manager as Capsule;

import('lib.pkp.classes.db.SchemaDAO');
import('lib.pkp.classes.submission.Genre'); // GENRE_CATEGORY_... constants
import('lib.pkp.classes.plugins.PKPPubIdPluginDAO');
import('lib.pkp.classes.submission.SubmissionFile');

abstract class PKPSubmissionFileDAO extends SchemaDAO implements PKPPubIdPluginDAO {

	/** @copydoc SchemaDAO::$schemaName */
	public $schemaName = SCHEMA_SUBMISSION_FILE;

	/** @copydoc SchemaDAO::$tableName */
	public $tableName = 'submission_files';

	/** @copydoc SchemaDAO::$settingsTableName */
	public $settingsTableName = 'submission_file_settings';

	/** @copydoc SchemaDAO::$primaryKeyColumn */
	public $primaryKeyColumn = 'submission_file_id';

	/** @copydoc SchemaDAO::$primaryTableColumns */
	public $primaryTableColumns = [
		'assocId' => 'assoc_id',
		'assocType' => 'assoc_type',
		'createdAt' => 'created_at',
		'fileId' => 'file_id',
		'fileStage' => 'file_stage',
		'genreId' => 'genre_id',
		'id' => 'submission_file_id',
		'sourceSubmissionFileId' => 'source_submission_file_id',
		'submissionId' => 'submission_id',
		'updatedAt' => 'updated_at',
		'uploaderUserId' => 'uploader_user_id',
		'viewable' => 'viewable',
	];

	/**
	 * Create a new DataObject of the appropriate class
	 *
	 * @return DataObject
	 */
	public function newDataObject() {
		return new SubmissionFile();
	}

	/**
	 * @copydoc SchemaDAO::getById
	 */
	public function getById($objectId) {
		$row = Capsule::table($this->tableName . ' as sf')
			->leftJoin('submissions as s', 's.submission_id', '=', 'sf.submission_id')
			->leftJoin('files as f', 'f.file_id', '=', 'sf.file_id')
			->where($this->primaryKeyColumn, '=', (int) $objectId)
			->select(['sf.*', 'f.*', 's.locale as locale'])
			->first();
		return $row ? $this->_fromRow((array) $row) : null;
	}

	/**
	 * @copydoc SchemaDAO::_fromRow()
	 */
	public function _fromRow($primaryRow) {
		$submissionFile = parent::_fromRow($primaryRow);
		$submissionFile->setData('locale', $primaryRow['locale']);
		$submissionFile->setData('path', $primaryRow['path']);
		$submissionFile->setData('mimetype', $primaryRow['mimetype']);

		return $submissionFile;
	}

	/**
	 * @copydoc SchemaDAO::insertObject
	 */
	public function insertObject($submissionFile) {
		parent::insertObject($submissionFile);

		Capsule::table('submission_file_revisions')->insert([
			'submission_file_id' => $submissionFile->getId(),
			'file_id' => $submissionFile->getData('fileId'),
		]);

		if (in_array($submissionFile->getData('assocType'), [ASSOC_TYPE_REVIEW_ROUND, ASSOC_TYPE_REVIEW_ASSIGNMENT])) {
			if ($submissionFile->getData('assocType') === ASSOC_TYPE_REVIEW_ROUND) {
				$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /* @var $reviewRoundDao ReviewRoundDAO */
				$reviewRound = $reviewRoundDao->getById($submissionFile->getData('assocId'));
			} elseif ($submissionFile->getData('assocType') === ASSOC_TYPE_REVIEW_ASSIGNMENT) {
				$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /* @var $reviewAssignmentDao ReviewAssignmentDAO */
				$reviewAssignment = $reviewAssignmentDao->getById($submissionFile->getData('assocId'));
				$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /* @var $reviewRoundDao ReviewRoundDAO */
				$reviewRound = $reviewRoundDao->getById($reviewAssignment->getReviewRoundId());
			}
			if (!$reviewRound) {
				throw new Exception('Review round not found for adding submission file.');
			}
			Capsule::table('review_round_files')->insert([
				'submission_id' => $submissionFile->getData('submissionId'),
				'review_round_id' => $reviewRound->getId(),
				'stage_id' => $reviewRound->getStageId(),
				'submission_file_id' => $submissionFile->getId(),
			]);
		}

		return $submissionFile->getId();
	}

	/**
	 * @copydoc SchemaDAO::updateObject()
	 */
	public function updateObject($submissionFile)	{
		parent::updateObject($submissionFile);

		$hasFileId = Capsule::table('submission_file_revisions')
			->where('submission_file_id', '=', $submissionFile->getId())
			->where('file_id', '=', $submissionFile->getData('fileId'))
			->exists();

		if (!$hasFileId) {
			Capsule::table('submission_file_revisions')->insert([
				'submission_file_id' => $submissionFile->getId(),
				'file_id' => $submissionFile->getData('fileId'),
			]);
		}
	}

	/**
	 * @copydoc SchemaDAO::deleteById()
	 */
	public function deleteById($submissionFileId) {
		Capsule::table('submission_file_revisions')
			->where('submission_file_id', '=', $submissionFileId)
			->delete();

		Capsule::table('review_round_files')
			->where('submission_file_id', '=', $submissionFileId)
			->delete();

		Capsule::table('review_files')
			->where('submission_file_id', '=', $submissionFileId)
			->delete();

		parent::deleteById($submissionFileId);
	}

	/**
	 * Get the files for each revision of a submission file
	 *
	 * @param int $submissionFileId
	 * @return Illuminate\Support\Collection
	 */
	public function getRevisions($submissionFileId) {
		return Capsule::table('submission_file_revisions as sfr')
			->leftJoin('files as f', 'f.file_id', '=', 'sfr.file_id')
			->where('submission_file_id', '=', $submissionFileId)
			->orderBy('revision_id', 'desc')
			->select(['f.file_id as fileId', 'f.path', 'f.mimetype'])
			->get();
	}


	//
	// Public methods
	//
	/**
	 * Retrieve file by public file ID
	 * @param $pubIdType string One of the NLM pub-id-type values or
	 * 'other::something' if not part of the official NLM list
	 * (see <http://dtd.nlm.nih.gov/publishing/tag-library/n-4zh0.html>).
	 * @param $pubId string
	 * @param $submissionId int optional
	 * @param $contextId int optional
	 * @return SubmissionFile|null
	 */
	function getByPubId($pubIdType, $pubId, $submissionId = null, $contextId = null) {
		if (empty($pubId)) {
			return null;
		}

		$submissionFileId = Capsule::table('submission_files as sf')
			->leftJoin('submission_file_settings as sfs', 'sfs.submission_file_id', '=' , 'sf.submission_file_id')
			->where('sf.submission_id', '=', $submissionId)
			->where(function($q) use ($pubIdType, $pubId) {
				$q->where('sfs.setting_name', '=', 'pub-id::' . $pubIdType);
				$q->where('sfs.setting_value', '=', $pubId);
			})
			->value('sf.submission_file_id');

		if (empty($submissionFileId)) {
			return null;
		}

		$submissionFile = Services::get('submissionFile')->get($submissionFileId);

		if ($submissionFile->getData('fileStage') !== SUBMISSION_FILE_PROOF) {
			return $submissionFile;
		}

		return null;
	}

	/**
	 * Retrieve file by public ID or submissionFileId
	 *
	 * @param string|int $bestId Publisher id or submissionFileId
	 * @param int $submissionId
	 * @return SubmissionFile|null
	 */
	function getByBestId($bestId, $submissionId) {
		$submissionFile = null;
		if ($bestId != '') $submissionFile = $this->getByPubId('publisher-id', $bestId, $submissionId, null);
		if (!isset($submissionFile)) {
			$submissionFile = Services::get('submissionFile')->get($bestId);
		}
		if ($submissionFile && in_array($submissionFile->getData('fileStage'), [SUBMISSION_FILE_PROOF, SUBMISSION_FILE_DEPENDENT])) {
			return $submissionFile;
		}
		return null;
	}

	/**
	 * Assign file to a review round.
	 * @param $submissionFileId int The file to be assigned.
	 * @param $reviewRound ReviewRound
	 */
	function assignRevisionToReviewRound($submissionFileId, $reviewRound) {

		// Avoid duplication errors -- clear out any existing entries
		$this->deleteReviewRoundAssignment($submissionFileId);

		$this->update(
			'INSERT INTO review_round_files
				(submission_id, review_round_id, stage_id, submission_file_id)
			VALUES (?, ?, ?, ?)',
			[
				(int) $reviewRound->getSubmissionId(),
				(int) $reviewRound->getId(),
				(int) $reviewRound->getStageId(),
				(int) $submissionFileId
			]
		);
	}

	/**
	 * Remove a specific file assignment from a review round.
	 * @param $submissionFileId int The file id.
	 */
	function deleteReviewRoundAssignment($submissionFileId) {
		// Remove currently assigned review files.
		$this->update(
			'DELETE FROM review_round_files
			WHERE submission_file_id = ?',
			[(int) $submissionFileId]
		);
	}


	//
	// Protected helper methods
	//


	/**
	 * Checks if public identifier exists (other than for the specified
	 * submission file ID, which is treated as an exception).
	 * @param $pubIdType string One of the NLM pub-id-type values or
	 * 'other::something' if not part of the official NLM list
	 * (see <http://dtd.nlm.nih.gov/publishing/tag-library/n-4zh0.html>).
	 * @param $pubId string
	 * @param $fileId int An ID to be excluded from the search.
	 * @param $contextId int
	 * @return boolean
	 */
	function pubIdExists($pubIdType, $pubId, $excludePubObjectId, $contextId) {
		$result = $this->retrieve(
			'SELECT COUNT(*) AS row_count
			FROM submission_file_settings sfs
				INNER JOIN submission_files sf ON sfs.submission_file_id = sf.submission_file_id
				INNER JOIN submissions s ON sf.submission_id = s.submission_id
			WHERE sfs.setting_name = ? AND sfs.setting_value = ? AND sfs.submission_file_id <> ? AND s.context_id = ?',
			[
				'pub-id::' . $pubIdType,
				$pubId,
				(int) $excludePubObjectId,
				(int) $contextId
			]
		);
		$row = $result->current();
		return $row ? (boolean) $row->row_count : false;
	}

	/**
	 * @copydoc PKPPubIdPluginDAO::changePubId()
	 */
	function changePubId($pubObjectId, $pubIdType, $pubId) {
		$this->replace(
			'submission_file_settings',
			[
				'submission_file_id' => (int) $pubObjectId,
				'locale' => '',
				'setting_name' => 'pub-id::' . $pubIdType,
				'setting_type' => 'string',
				'setting_value' => (string) $pubId
			],
			['submission_file_id', 'locale', 'setting_name']
		);
		$this->flushCache();
	}

	/**
	 * @copydoc PKPPubIdPluginDAO::deletePubId()
	 */
	function deletePubId($pubObjectId, $pubIdType) {
		$settingName = 'pub-id::' . $pubIdType;
		$this->update(
			'DELETE FROM submission_file_settings WHERE setting_name = ? AND submission_file_id = ?',
			[$settingName, (int) $pubObjectId]
		);
		$this->flushCache();
	}

	/**
	 * @copydoc PKPPubIdPluginDAO::deleteAllPubIds()
	 */
	function deleteAllPubIds($contextId, $pubIdType) {
		$settingName = 'pub-id::'.$pubIdType;

		$submissionDao = DAORegistry::getDAO('SubmissionDAO'); /* @var $submissionDao SubmissionDAO */
		$submissions = $submissionDao->getByContextId($contextId);
		while ($submission = $submissions->next()) {
			$submissionFileIds = Services::get('submissionFile')->getIds([
				'submissionIds' => [$submission->getId()],
			]);
			foreach ($submissionFileIds as $submissionFileId) {
				$this->update(
					'DELETE FROM submission_file_settings WHERE setting_name = ? AND submission_file_id = ?',
					[$settingName, $submissionFileId]
				);
			}
		}
		$this->flushCache();
	}
}

