<?php

/**
 * @file controllers/grid/users/reviewer/ReviewerGridCellProvider.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReviewerGridCellProvider
 * @ingroup controllers_grid_users_reviewer
 *
 * @brief Base class for a cell provider that can retrieve labels for reviewer grid rows
 */

import('lib.pkp.classes.controllers.grid.DataObjectGridCellProvider');

import('lib.pkp.classes.linkAction.request.AjaxModal');
import('lib.pkp.classes.linkAction.request.AjaxAction');

class ReviewerGridCellProvider extends DataObjectGridCellProvider {

	/** @var boolean Is the current user assigned as an author to this submission */
	public $_isCurrentUserAssignedAuthor;

	/**
	 * Constructor
	 * @param $isCurrentUserAssignedAuthor boolean Is the current user assigned
	 *  as an author to this submission?
	 */
	public function __construct($isCurrentUserAssignedAuthor) {
		parent::__construct();
		$this->_isCurrentUserAssignedAuthor = $isCurrentUserAssignedAuthor;
	}

	//
	// Template methods from GridCellProvider
	//
	/**
	 * Gathers the state of a given cell given a $row/$column combination
	 * @param $row GridRow
	 * @param $column GridColumn
	 * @return string
	 */
	function getCellState($row, $column) {
		$reviewAssignment = $row->getData();
		$columnId = $column->getId();
		assert(is_a($reviewAssignment, 'DataObject') && !empty($columnId));
		switch ($columnId) {
			case 'name':
			case 'method':
				return '';
			case 'considered':
			case 'actions':
				return $reviewAssignment->getStatus();
		}
	}

	/**
	 * Extracts variables for a given column from a data element
	 * so that they may be assigned to template before rendering.
	 * @param $row GridRow
	 * @param $column GridColumn
	 * @return array
	 */
	function getTemplateVarsFromRowColumn($row, $column) {
		$element = $row->getData();
		$columnId = $column->getId();
		assert(is_a($element, 'DataObject') && !empty($columnId));
		switch ($columnId) {
			case 'name':
				$isReviewBlind = in_array($element->getReviewMethod(), array(SUBMISSION_REVIEW_METHOD_BLIND, SUBMISSION_REVIEW_METHOD_DOUBLEBLIND));
				if ($this->_isCurrentUserAssignedAuthor && $isReviewBlind) {
					return array('label' => __('editor.review.anonymousReviewer'));
				}
				return array('label' => $element->getReviewerFullName());

			case 'method':
				return array('label' => __($element->getReviewMethodKey()));

			case 'considered':
				return array('label' => $this->_getStatusText($this->getCellState($row, $column), $row));

			case 'actions':
				// Only attach actions to this column. See self::getCellActions()
				return array('label' => '');
		}

		return parent::getTemplateVarsFromRowColumn($row, $column);
	}

	/**
	 * Get cell actions associated with this row/column combination
	 * @param $row GridRow
	 * @param $column GridColumn
	 * @return array an array of LinkAction instances
	 */
	function getCellActions($request, $row, $column, $position = GRID_ACTION_POSITION_DEFAULT) {
		$reviewAssignment = $row->getData();

		// Authors can't perform action on reviews
		if ($this->_isCurrentUserAssignedAuthor) {
			return array();
		}

		$actionArgs = array(
			'submissionId' => $reviewAssignment->getSubmissionId(),
			'reviewAssignmentId' => $reviewAssignment->getId(),
			'stageId' => $reviewAssignment->getStageId()
		);

		$router = $request->getRouter();
		$action = false;
		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->getById($reviewAssignment->getSubmissionId());

		// Only attach actions to the actions column. The actions and status
		// columns share state values.
		$columnId = $column->getId();
		if ($columnId == 'actions') {
			switch($this->getCellState($row, $column)) {
				case REVIEW_ASSIGNMENT_STATUS_RESPONSE_OVERDUE:
				case REVIEW_ASSIGNMENT_STATUS_REVIEW_OVERDUE:
					import('lib.pkp.controllers.api.task.SendReminderLinkAction');
					return array(new SendReminderLinkAction($request, 'editor.review.reminder', $actionArgs));
				case REVIEW_ASSIGNMENT_STATUS_COMPLETE:
					import('lib.pkp.controllers.api.task.SendThankYouLinkAction');
					import('lib.pkp.controllers.review.linkAction.UnconsiderReviewLinkAction');
					return array(
						new SendThankYouLinkAction($request, 'editor.review.thankReviewer', $actionArgs),
						new UnconsiderReviewLinkAction($request, $reviewAssignment, $submission),
					);
				case REVIEW_ASSIGNMENT_STATUS_THANKED:
					import('lib.pkp.controllers.review.linkAction.UnconsiderReviewLinkAction');
					return array(new UnconsiderReviewLinkAction($request, $reviewAssignment, $submission));
				case REVIEW_ASSIGNMENT_STATUS_RECEIVED:
					$user = $request->getUser();
					import('lib.pkp.controllers.review.linkAction.ReviewNotesLinkAction');
					return array(new ReviewNotesLinkAction($request, $reviewAssignment, $submission, $user, 'grid.users.reviewer.ReviewerGridHandler', true));
			}

		}
		return parent::getCellActions($request, $row, $column, $position);
	}

	/**
	 * Provide meaningful locale keys for the various grid status states.
	 * @param string $state
	 * @param $row GridRow
	 * @return string
	 */
	function _getStatusText($state, $row) {
		$reviewAssignment = $row->getData();
		switch ($state) {
			case REVIEW_ASSIGNMENT_STATUS_AWAITING_RESPONSE:
				return '<span class="state">'.__('editor.review.requestSent').'</span><span class="details">'.__('editor.review.responseDue', array('date' => substr($reviewAssignment->getDateResponseDue(),0,10))).'</span>';
			case REVIEW_ASSIGNMENT_STATUS_ACCEPTED:
				return '<span class="state">'.__('editor.review.requestAccepted').'</span><span class="details">'.__('editor.review.reviewDue', array('date' => substr($reviewAssignment->getDateDue(),0,10))).'</span>';
			case REVIEW_ASSIGNMENT_STATUS_COMPLETE:
				return $this->_getStatusWithRecommendation('common.complete', $reviewAssignment);
			case REVIEW_ASSIGNMENT_STATUS_REVIEW_OVERDUE:
				return '<span class="state overdue">'.__('common.overdue').'</span><span class="details">'.__('editor.review.reviewDue', array('date' => substr($reviewAssignment->getDateDue(),0,10))).'</span>';
			case REVIEW_ASSIGNMENT_STATUS_RESPONSE_OVERDUE:
				return '<span class="state overdue">'.__('common.overdue').'</span><span class="details">'.__('editor.review.responseDue', array('date' => substr($reviewAssignment->getDateResponseDue(),0,10))).'</span>';
			case REVIEW_ASSIGNMENT_STATUS_DECLINED:
				return '<span class="state declined" title="' . __('editor.review.requestDeclined.tooltip') . '">'.__('editor.review.requestDeclined').'</span>';
			case REVIEW_ASSIGNMENT_STATUS_CANCELLED:
				return '<span class="state declined" title="' . __('editor.review.requestCancelled.tooltip') . '">'.__('editor.review.requestCancelled').'</span>';
			case REVIEW_ASSIGNMENT_STATUS_RECEIVED:
				return  $this->_getStatusWithRecommendation('editor.review.reviewSubmitted', $reviewAssignment);
			case REVIEW_ASSIGNMENT_STATUS_THANKED:
				return  $this->_getStatusWithRecommendation('editor.review.reviewerThanked', $reviewAssignment);
			default:
				return '';
		}
	}

	/**
	 * Retrieve a formatted HTML string that displays the state of the review
	 * with the review recommendation if one exists. Or return just the state.
	 * Only works with some states.
	 *
	 * @param string $statusKey Locale key for status text
	 * @param ReviewAssignment $reviewAssignment
	 * @return string
	 */
	function _getStatusWithRecommendation($statusKey, $reviewAssignment) {

		if (!$reviewAssignment->getRecommendation()) {
			return __($statusKey);
		}

		return '<span class="state">'.__($statusKey).'</span><span class="details">'.__('submission.recommendation', array('recommendation' => $reviewAssignment->getLocalizedRecommendation())).'</span>';
	}
}


