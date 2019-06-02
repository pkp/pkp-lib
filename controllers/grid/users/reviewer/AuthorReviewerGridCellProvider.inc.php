<?php

/**
 * @file controllers/grid/users/reviewer/AuthorReviewerGridCellProvider.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AuthorReviewerGridCellProvider
 * @ingroup controllers_grid_users_reviewer
 *
 * @brief Base class for a cell provider that can retrieve labels for reviewer grid rows in author workflow
 */

import('lib.pkp.classes.controllers.grid.DataObjectGridCellProvider');

import('lib.pkp.classes.linkAction.request.AjaxModal');
import('lib.pkp.classes.linkAction.request.AjaxAction');

class AuthorReviewerGridCellProvider extends DataObjectGridCellProvider {

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
		$actionArgs = array(
			'submissionId' => $reviewAssignment->getSubmissionId(),
			'reviewAssignmentId' => $reviewAssignment->getId(),
			'stageId' => $reviewAssignment->getStageId(),
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
				case REVIEW_ASSIGNMENT_STATUS_COMPLETE:
				case REVIEW_ASSIGNMENT_STATUS_THANKED:
				case REVIEW_ASSIGNMENT_STATUS_RECEIVED:
					$user = $request->getUser();
					import('lib.pkp.controllers.review.linkAction.ReviewNotesLinkAction');
					return array(new ReviewNotesLinkAction($request, $reviewAssignment, $submission, $user, 'grid.users.reviewer.AuthorReviewerGridHandler', true));
				default:
					return null;
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
				return '<span class="state declined">'.__('common.declined').'</span>';
			case REVIEW_ASSIGNMENT_STATUS_CANCELLED:
				return '<span class="state cancelled">'.__('common.cancelled').'</span>';
			case REVIEW_ASSIGNMENT_STATUS_RECEIVED:
				return  $this->_getStatusWithRecommendation('editor.review.reviewSubmitted', $reviewAssignment);
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
