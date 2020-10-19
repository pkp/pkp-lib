<?php
/**
 * @file classes/services/QueryBuilders/PKPSubmissionFileQueryBuilder.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionFileQueryBuilder
 * @ingroup query_builders
 *
 * @brief Class for building database queries for submission files
 */

namespace PKP\Services\QueryBuilders;

use Illuminate\Database\Capsule\Manager as Capsule;
use PKP\Services\QueryBuilders\Interfaces\EntityQueryBuilderInterface;

import('lib.pkp.classes.submission.SubmissionFile'); // SUBMISSION_FILE_ constants

class PKPSubmissionFileQueryBuilder implements EntityQueryBuilderInterface {

	/** @var array get submission files for one or more file stages */
	protected $fileStages = [];

	/** @var array get submission files for one or more genres */
	protected $genreIds = [];

	/** @var array get submission files for one or more review rounds */
	protected $reviewRoundIds = [];

	/** @var array get submission files for one or more review assignments */
	protected $reviewIds = [];

	/** @var array get submission files for one or more submissions */
	protected $submissionIds = [];

	/** @var array get submission files matching one or more files */
	protected $fileIds = [];

	/** @var array get submission files matching one or more ASSOC_TYPE */
	protected $assocTypes = [];

	/** @var array get submission files matching an ASSOC_ID with one of the assocTypes */
	protected $assocIds = [];

	/** @var boolean include submission files in the SUBMISSION_FILE_DEPENDENT stage */
	protected $includeDependentFiles = false;

	/**
	 * Set fileStages filter
	 *
	 * @param array|int $fileStages
	 * @return \PKP\Services\QueryBuilders\PKPSubmissionFileQueryBuilder
	 */
	public function filterByFileStages($fileStages) {
		$this->fileStages = is_array($fileStages) ? $fileStages : [$fileStages];
		return $this;
	}

	/**
	 * Set genreIds filter
	 *
	 * @param array|int $genreIds
	 * @return \PKP\Services\QueryBuilders\PKPSubmissionFileQueryBuilder
	 */
	public function filterByGenreIds($genreIds) {
		$this->genreIds = is_array($genreIds) ? $genreIds : [$genreIds];
		return $this;
	}

	/**
	 * Set review rounds filter
	 *
	 * @param array|int $reviewRoundIds
	 * @return \PKP\Services\QueryBuilders\PKPSubmissionFileQueryBuilder
	 */
	public function filterByReviewRoundIds($reviewRoundIds) {
		$this->reviewRoundIds = is_array($reviewRoundIds) ? $reviewRoundIds : [$reviewRoundIds];
		return $this;
	}

	/**
	 * Set review assignments filter
	 *
	 * @param array|int $reviewIds
	 * @return \PKP\Services\QueryBuilders\PKPSubmissionFileQueryBuilder
	 */
	public function filterByReviewIds($reviewIds) {
		$this->reviewIds = is_array($reviewIds) ? $reviewIds : [$reviewIds];
		return $this;
	}

	/**
	 * Set submissionIds filter
	 *
	 * @param array|int $submissionIds
	 * @return \PKP\Services\QueryBuilders\PKPSubmissionFileQueryBuilder
	 */
	public function filterBySubmissionIds($submissionIds) {
		$this->submissionIds = is_array($submissionIds) ? $submissionIds : [$submissionIds];
		return $this;
	}

	/**
	 * Set fileIds filter
	 *
	 * @param array|int $fileIds
	 * @return \PKP\Services\QueryBuilders\PKPSubmissionFileQueryBuilder
	 */
	public function filterByFileIds($fileIds) {
		$this->fileIds = is_array($fileIds) ? $fileIds : [$fileIds];
		return $this;
	}

	/**
	 * Set assocType and assocId filters
	 *
	 * @param array|int $assocTypes One or more of the ASSOC_TYPE_ constants
	 * @param array|int $assocIds Match with ids for these assoc types
	 * @return \PKP\Services\QueryBuilders\PKPSubmissionFileQueryBuilder
	 */
	public function filterByAssoc($assocTypes, $assocIds = []) {
		$this->assocTypes = is_array($assocTypes) ? $assocTypes : [$assocTypes];
		if (!empty($assocIds)) {
			$this->assocIds = is_array($assocIds) ? $assocIds : [$assocIds];
		}
		return $this;
	}

	/**
	 * Set uploaderUserIds filter
	 *
	 * @param array|int $uploaderUserIds
	 * @return \PKP\Services\QueryBuilders\PKPSubmissionFileQueryBuilder
	 */
	public function filterByUploaderUserIds($uploaderUserIds) {
		$this->uploaderUserIds = is_array($uploaderUserIds) ? $uploaderUserIds : [$uploaderUserIds];
		return $this;
	}

	/**
	 * Whether or not to include dependent files in the results
	 *
	 * @param boolean $includeDependentFiles
	 * @return \PKP\Services\QueryBuilders\PKPSubmissionFileQueryBuilder
	 */
	public function includeDependentFiles($includeDependentFiles) {
		$this->includeDependentFiles = (boolean) $includeDependentFiles;
		return $this;
	}

	/**
	 * @copydoc PKP\Services\QueryBuilders\Interfaces\EntityQueryBuilderInterface::getCount()
	 */
	public function getCount() {
		return $this
			->getQuery()
			->select('sf.submission_file_id')
			->get()
			->count();
	}

	/**
	 * @copydoc PKP\Services\QueryBuilders\Interfaces\EntityQueryBuilderInterface::getIds()
	 */
	public function getIds() {
		return $this
			->getQuery()
			->select('sf.submission_file_id')
			->pluck('sf.submission_file_id')
			->toArray();
	}

	/**
	 * Execute query builder
	 *
	 * @return object Query object
	 */
	public function getQuery() {
		$this->columns = ['sf.*'];

		$q = Capsule::table('submission_files as sf');

		if (!empty($this->submissionIds)) {
			$q->whereIn('sf.submission_id', $this->submissionIds);
		}

		if (!empty($this->fileStages)) {
			$q->whereIn('sf.file_stage', $this->fileStages);
		}

		if (!empty($this->genreIds)) {
			$q->whereIn('sf.genre_id', $this->genreIds);
		}

		if (!empty($this->fileIds)) {
			$q->leftJoin('submission_file_revisions as sfr', 'sfr.submission_file_id', '=', 'sf.submission_file_id')
				->whereIn('sfr.file_id', $this->fileIds);
		}

		if (!empty($this->reviewRoundIds)) {
			$q->join('review_round_files as rr', 'rr.submission_file_id', '=', 'sf.submission_file_id')
				->whereIn('rr.review_round_id', $this->reviewRoundIds);
		}

		if (!empty($this->reviewIds)) {
			$q->join('review_files as rf', 'rf.submission_file_id', '=', 'sf.submission_file_id')
				->whereIn('rf.review_id', $this->reviewIds);
		}

		if (!empty($this->assocTypes)) {
			$q->whereIn('sf.assoc_type', $this->assocTypes);

			if (!empty($this->assocIds)) {
				$q->whereIn('sf.assoc_id', $this->assocIds);
			}
		}

		if (!empty($this->uploaderUserIds)) {
			$q->whereIn('sf.uploader_user_id', $this->uploaderUserIds);
		}

		if (empty($this->includeDependentFiles) && !in_array(SUBMISSION_FILE_DEPENDENT, $this->fileStages)) {
			$q->where('sf.file_stage', '!=', SUBMISSION_FILE_DEPENDENT);
		}

		// Add app-specific query statements
		\HookRegistry::call('SubmissionFile::getMany::queryObject', array(&$q, $this));

		// Only return results for the latest revision
		$q->select($this->columns);

		return $q;
	}

	/**
	 * Get the file ids for each revision of a submission file
	 *
	 * @param int $submissionFileId
	 * @return array
	 */
	public function getRevisionFileIds($submissionFileId) {
		return Capsule::table('submission_file_revisions')
			->where('submission_file_id', '=', $submissionFileId)
			->orderBy('revision_id', 'desc')
			->pluck('file_id')
			->toArray();
	}
}
