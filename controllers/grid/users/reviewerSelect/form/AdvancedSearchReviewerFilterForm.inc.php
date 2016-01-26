<?php

/**
 * @file controllers/grid/users/reviewerSelect/form/AdvancedSearchReviewerFilterForm.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AdvancedSearchReviewerFilterForm
 * @ingroup controllers_grid_users_reviewer_form
 *
 * @brief Form to filter the reviewer select grid.
 */

import('lib.pkp.classes.form.Form');

class AdvancedSearchReviewerFilterForm extends Form {
	/** @var The submission associated with the review assignment **/
	var $_submission;

	/** @var int */
	var $_stageId;

	/** @var int */
	var $_reviewRoundId;

	/**
	 * Constructor.
	 * @param $submission Submission
	 * @param $stageId int
	 * @param $reviewRoundId int
	 */
	function AdvancedSearchReviewerFilterForm($submission, $stageId, $reviewRoundId) {
		parent::Form();
		$this->_submission = $submission;
		$this->_stageId = $stageId;
		$this->_reviewRoundId = $reviewRoundId;
		$this->setTemplate('controllers/grid/users/reviewer/form/advancedSearchReviewerFilterForm.tpl');
	}

	/**
	 * Get the submission
	 * @return Submission
	 */
	function getSubmission() {
		return $this->_submission;
	}

	/**
	 * Get the stage id.
	 * @return int
	 */
	function getStageId() {
		return $this->_stageId;
	}

	/**
	 * Get the review round id.
	 * @return int
	 */
	function getReviewRoundId() {
		return $this->_reviewRoundId;
	}

	/*
	 * Initialize the filter form inputs
	 * @param $filterData array
	 * @param $request PKPRequest
	 */
	function initData($filterData, $request) {
		$this->_data = $filterData;

		$submission = $this->getSubmission();
		$this->setData('submissionId', $submission->getId());
		$this->setData('stageId', $this->getStageId());
		$this->setData('reviewRoundId', $this->getReviewRoundId());

		return parent::initData($filterData, $request);
	}

	/**
	 * Assign form data to user-submitted data.
	 * @see Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array(
			'name',
			'doneEnabled',
			'doneMin',
			'doneMax',
			'avgEnabled',
			'avgMin',
			'avgMax',
			'lastEnabled',
			'lastMin',
			'lastMax',
			'activeEnabled',
			'activeMin',
			'activeMax',
			'keywords')
		);

		$keywords = $this->getData('keywords');
		if (is_array($keywords) && array_key_exists('interests', $keywords)) {
			$interests = $keywords['interests'];
			if ($interests != null && is_array($interests)) {
				// The interests are coming in encoded -- Decode them for DB storage
				$this->setData('interestSearchKeywords', array_map('urldecode', $interests));
			}
		}
		parent::readInputData();
	}

	/**
	 * Get the filter's data in an array to send back to the grid
	 * @return array
	 */
	function getFilterSelectionData() {
		$reviewerValues = array(
			'name' => (string) $this->getData('name'),
			'doneEnabled' => (bool) $this->getData('doneEnabled'),
			'doneMin' => (int) $this->getData('doneMin'),
			'doneMax' => (int) $this->getData('doneMax'),
			'avgEnabled' => (bool) $this->getData('avgEnabled'),
			'avgMin' => (int) $this->getData('avgMin'),
			'avgMax' => (int) $this->getData('avgMax'),
			'lastEnabled' => (bool) $this->getData('lastEnabled'),
			'lastMin' => (int) $this->getData('lastMin'),
			'lastMax' => (int) $this->getData('lastMax'),
			'activeEnabled' => (bool) $this->getData('activeEnabled'),
			'activeMin' => (int) $this->getData('activeMin'),
			'activeMax' => (int) $this->getData('activeMax')
		);

		return $filterSelectionData = array(
			'reviewerValues' => $reviewerValues,
			'interestSearchKeywords' => $this->getData('interestSearchKeywords')
		);
	}
}

?>
