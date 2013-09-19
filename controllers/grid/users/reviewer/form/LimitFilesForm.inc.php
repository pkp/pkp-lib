<?php

/**
 * @file controllers/grid/users/reviewer/form/LimitFilesForm.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class LimitFilesForm
 * @ingroup controllers_grid_users_reviewer_form
 *
 * @brief Allow the editor to limit the available files to an assigned
 * reviewer after the assignment has taken place.
 */

import('lib.pkp.classes.form.Form');

class LimitFilesForm extends Form {
	/** @var ReviewAssignment */
	var $_reviewAssignment;

	/** @var ReviewRound */
	var $_reviewRound;

	/**
	 * Constructor.
	 * @param $reviewAssignment ReviewAssignment
	 */
	function LimitFilesForm($reviewAssignment) {
		$this->_reviewAssignment = $reviewAssignment;
		assert(is_a($this->_reviewAssignment, 'ReviewAssignment'));

		$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
		$this->_reviewRound = $reviewRoundDao->getById($reviewAssignment->getReviewRoundId());
		assert(is_a($this->_reviewRound, 'ReviewRound'));

		parent::Form('controllers/grid/users/reviewer/form/limitFilesForm.tpl');
		$this->addCheck(new FormValidatorPost($this));
	}

	//
	// Overridden template methods
	//
	/**
	 * Fetch
	 * @param $request PKPRequest
	 * @see Form::fetch()
	 */
	function fetch($request) {
		$templateMgr = TemplateManager::getManager($request);

		// Pass along various necessary parameters from request
		$templateMgr->assign('stageId', $this->_reviewAssignment->getStageId());
		$templateMgr->assign('reviewRoundId', $this->_reviewRound->getId());
		$templateMgr->assign('submissionId', $this->_reviewAssignment->getSubmissionId());
		$templateMgr->assign('reviewAssignmentId', $this->_reviewAssignment->getId());

		return parent::fetch($request);
	}

	/**
	 * Assign form data to user-submitted data.
	 * @see Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array(
			'selectedFiles',
		));
	}

	/**
	 * Save review assignment
	 * @param $request PKPRequest
	 */
	function execute() {
		// Get the list of available files for this review.
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
		import('lib.pkp.classes.submission.SubmissionFile'); // File constants
		$submissionFiles = $submissionFileDao->getLatestNewRevisionsByReviewRound($this->_reviewRound, SUBMISSION_FILE_REVIEW_FILE);
		$selectedFiles = (array) $this->getData('selectedFiles');

		// Revoke all, then grant selected.
		$reviewFilesDao = DAORegistry::getDAO('ReviewFilesDAO');
		$reviewFilesDao->revokeByReviewId($this->_reviewAssignment->getId());
		foreach ($submissionFiles as $submissionFile) {
			if (in_array($submissionFile->getFileId(), $selectedFiles)) {
				$reviewFilesDao->grant($this->_reviewAssignment->getId(), $submissionFile->getFileId());
			}
		}
	}
}

?>
