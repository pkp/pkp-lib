<?php
/**
 * @file controllers/grid/files/review/ReviewGridDataProvider.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewGridDataProvider
 * @ingroup controllers_grid_files_review
 *
 * @brief Provide access to review file data for grids.
 */

import('lib.pkp.controllers.grid.files.SubmissionFilesGridDataProvider');

class ReviewGridDataProvider extends SubmissionFilesGridDataProvider {
	/** @var boolean */
	protected $_showAll;

	/**
	 * Constructor
	 * @copydoc SubmissionFilesGridDataProvider::__construct()
	 * @param $showAll boolean True iff all review round files should be included.
	 */
	function __construct($fileStageId, $viewableOnly = false, $showAll = false) {
		$this->_showAll = $showAll;
		parent::__construct($fileStageId, $viewableOnly);
	}


	//
	// Implement template methods from GridDataProvider
	//
	/**
	 * @copydoc GridDataProvider::getAuthorizationPolicy()
	 */
	function getAuthorizationPolicy($request, $args, $roleAssignments) {
		// Get the parent authorization policy.
		$policy = parent::getAuthorizationPolicy($request, $args, $roleAssignments);

		// Add policy to ensure there is a review round id.
		import('lib.pkp.classes.security.authorization.internal.ReviewRoundRequiredPolicy');
		$policy->addPolicy(new ReviewRoundRequiredPolicy($request, $args));

		return $policy;
	}

	/**
	 * @copydoc GridDataProvider::getRequestArgs()
	 */
	function getRequestArgs() {
		$reviewRound = $this->getReviewRound();
		return array_merge(parent::getRequestArgs(), array(
			'reviewRoundId' => $reviewRound->getId()
			)
		);
	}

	/**
	 * @copydoc GridDataProvider::loadData()
	 */
	function loadData($filter = array()) {
		// Get all review files assigned to this submission.
		$params = [
			'submissionId' => [$this->getSubmission()->getId()],
			'reviewRoundIds' => [$this->getReviewRound()->getId()],
		];
		if (!$this->_showAll) {
			$params['fileStages'] = [(int) $this->getFileStage()];
		}
		$submissionFilesIterator = Services::get('submissionFile')->getMany($params);
		return $this->prepareSubmissionFileData(iterator_to_array($submissionFilesIterator), $this->_viewableOnly, $filter);
	}

	//
	// Overridden public methods from FilesGridDataProvider
	//
	/**
	 * @copydoc FilesGridDataProvider::getSelectAction()
	 */
	function getSelectAction($request) {
		import('lib.pkp.controllers.grid.files.fileList.linkAction.SelectReviewFilesLinkAction');
		$reviewRound = $this->getReviewRound();
		$modalTitle = __('editor.submission.review.currentFiles', array('round' => $reviewRound->getRound()));
		return new SelectReviewFilesLinkAction(
			$request, $reviewRound,
			__('editor.submission.uploadSelectFiles'),
			$modalTitle
		);
	}

	/**
	 * @copydoc FilesGridDataProvider::getAddFileAction()
	 */
	function getAddFileAction($request) {
		import('lib.pkp.controllers.api.file.linkAction.AddFileLinkAction');
		$submission = $this->getSubmission();
		$reviewRound = $this->getReviewRound();

		return new AddFileLinkAction(
			$request, $submission->getId(), $this->getStageId(),
			$this->getUploaderRoles(), $this->getFileStage(),
			null, null, $reviewRound->getId()
		);
	}

	/**
	 * Get the review round object.
	 * @return ReviewRound
	 */
	function getReviewRound() {
		$reviewRound = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ROUND);
		return $reviewRound;
	}
}


