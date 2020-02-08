<?php

/**
 * @file controllers/grid/eventLog/EventLogGridCellProvider.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EventLogGridCellProvider
 * @ingroup controllers_grid_publicationEntry
 *
 * @brief Cell provider for event log entries.
 */

import('lib.pkp.classes.controllers.grid.DataObjectGridCellProvider');

class EventLogGridCellProvider extends DataObjectGridCellProvider {

	/** @var boolean Is the current user assigned as an author to this submission */
	var $_isCurrentUserAssignedAuthor;

	/**
	 * Constructor
	 * @param boolean $isCurrentUserAssignedAuthor Is the current user assigned
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
			case 'date':
				return array('label' => is_a($element, 'EventLogEntry') ? $element->getDateLogged() : $element->getDateSent());
			case 'event':
				return array('label' => is_a($element, 'EventLogEntry') ? $element->getTranslatedMessage(null, $this->_isCurrentUserAssignedAuthor) : $element->getPrefixedSubject());
			case 'user':
				if (is_a($element, 'EventLogEntry')) {
					$userName = $element->getUserFullName();

					// Anonymize reviewer details where necessary
					if ($this->_isCurrentUserAssignedAuthor) {
						$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /* @var $reviewAssignmentDao ReviewAssignmentDAO */

						// Maybe anonymize reviewer log entries
						$reviewerLogTypes = array(
							SUBMISSION_LOG_REVIEW_ACCEPT,
							SUBMISSION_LOG_REVIEW_DECLINE,
							SUBMISSION_LOG_REVIEW_UNCONSIDERED,
							SUBMISSION_LOG_REVIEW_FILE,
							SUBMISSION_LOG_REVIEW_CANCEL,
							SUBMISSION_LOG_REVIEW_REVISION,
							SUBMISSION_LOG_REVIEW_RECOMMENDATION,
						);
						$params = $element->getParams();
						if (in_array($element->getEventType(), $reviewerLogTypes)) {
							$userName = __('editor.review.anonymousReviewer');
							if (isset($params['reviewAssignmentId'])) {
								$reviewAssignment = $reviewAssignmentDao->getById($params['reviewAssignmentId']);
								if ($reviewAssignment && $reviewAssignment->getReviewMethod() === SUBMISSION_REVIEW_METHOD_OPEN) {
									$userName = $reviewAssignment->getUserFullName();
								}
							}
						}

						// Maybe anonymize files submitted by reviewers
						if (isset($params['fileStage']) && $params['fileStage'] === SUBMISSION_FILE_REVIEW_ATTACHMENT) {
							assert(isset($params['fileId']) && isset($params['submissionId']));
							$blindAuthor = true;
							$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
							$submissionFile = $submissionFileDao->getLatestRevision($params['fileId']);
							if ($submissionFile && $submissionFile->getAssocType() === ASSOC_TYPE_REVIEW_ASSIGNMENT) {
								$reviewAssignment = $reviewAssignmentDao->getById($submissionFile->getAssocId());
								if (!$reviewAssignment || in_array($reviewAssignment->getReviewMethod(), array(SUBMISSION_REVIEW_METHOD_BLIND, SUBMISSION_REVIEW_METHOD_DOUBLEBLIND))) {
									$userName = __('editor.review.anonymousReviewer');
								}
							}
						}
					}
				} else {
					$userName = $element->getSenderFullName();
				}
				return array('label' => $userName);
			default:
				assert(false);
		}
	}
}


