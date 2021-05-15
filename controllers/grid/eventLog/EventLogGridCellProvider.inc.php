<?php

/**
 * @file controllers/grid/eventLog/EventLogGridCellProvider.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EventLogGridCellProvider
 * @ingroup controllers_grid_publicationEntry
 *
 * @brief Cell provider for event log entries.
 */

use PKP\controllers\grid\DataObjectGridCellProvider;
use PKP\controllers\grid\GridColumn;
use PKP\log\PKPSubmissionEventLogEntry;
use PKP\submission\SubmissionFile;

class EventLogGridCellProvider extends DataObjectGridCellProvider
{
    /** @var boolean Is the current user assigned as an author to this submission */
    public $_isCurrentUserAssignedAuthor;

    /**
     * Constructor
     *
     * @param boolean $isCurrentUserAssignedAuthor Is the current user assigned
     *  as an author to this submission?
     */
    public function __construct($isCurrentUserAssignedAuthor)
    {
        parent::__construct();
        $this->_isCurrentUserAssignedAuthor = $isCurrentUserAssignedAuthor;
    }

    //
    // Template methods from GridCellProvider
    //
    /**
     * Extracts variables for a given column from a data element
     * so that they may be assigned to template before rendering.
     *
     * @param $row \PKP\controllers\grid\GridRow
     * @param $column GridColumn
     *
     * @return array
     */
    public function getTemplateVarsFromRowColumn($row, $column)
    {
        $element = $row->getData();
        $columnId = $column->getId();
        assert($element instanceof \PKP\core\DataObject && !empty($columnId));
        switch ($columnId) {
            case 'date':
                return ['label' => is_a($element, 'EventLogEntry') ? $element->getDateLogged() : $element->getDateSent()];
            case 'event':
                return ['label' => is_a($element, 'EventLogEntry') ? $element->getTranslatedMessage(null, $this->_isCurrentUserAssignedAuthor) : $element->getPrefixedSubject()];
            case 'user':
                if (is_a($element, 'EventLogEntry')) {
                    $userName = $element->getUserFullName();

                    // Anonymize reviewer details where necessary
                    if ($this->_isCurrentUserAssignedAuthor) {
                        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */

                        // Maybe anonymize reviewer log entries
                        $reviewerLogTypes = [
                            PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_ACCEPT,
                            PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_DECLINE,
                            PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_UNCONSIDERED,
                        ];
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
                        if (isset($params['fileStage']) && $params['fileStage'] === SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT) {
                            assert(isset($params['fileId']) && isset($params['submissionId']));
                            $submissionFile = Services::get('submissionFile')->get($params['id']);
                            if ($submissionFile && $submissionFile->getData('assocType') === ASSOC_TYPE_REVIEW_ASSIGNMENT) {
                                $reviewAssignment = $reviewAssignmentDao->getById($submissionFile->getData('assocId'));
                                if (!$reviewAssignment || in_array($reviewAssignment->getReviewMethod(), [SUBMISSION_REVIEW_METHOD_ANONYMOUS, SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS])) {
                                    $userName = __('editor.review.anonymousReviewer');
                                }
                            }
                        }
                    }
                } else {
                    $userName = $element->getSenderFullName();
                }
                return ['label' => $userName];
            default:
                assert(false);
        }
    }
}
