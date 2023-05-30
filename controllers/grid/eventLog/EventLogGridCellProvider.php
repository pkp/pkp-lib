<?php

/**
 * @file controllers/grid/eventLog/EventLogGridCellProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EventLogGridCellProvider
 *
 * @ingroup controllers_grid_publicationEntry
 *
 * @brief Cell provider for event log entries.
 */

namespace PKP\controllers\grid\eventLog;

use APP\core\Application;
use APP\facades\Repo;
use PKP\controllers\grid\DataObjectGridCellProvider;
use PKP\controllers\grid\GridColumn;
use PKP\db\DAORegistry;
use PKP\log\event\EventLogEntry;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewAssignment\ReviewAssignmentDAO;
use PKP\submissionFile\SubmissionFile;

class EventLogGridCellProvider extends DataObjectGridCellProvider
{
    /** @var bool Is the current user assigned as an author to this submission */
    public $_isCurrentUserAssignedAuthor;

    /**
     * Constructor
     *
     * @param bool $isCurrentUserAssignedAuthor Is the current user assigned
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
     * @param \PKP\controllers\grid\GridRow $row
     * @param GridColumn $column
     *
     * @return array
     */
    public function getTemplateVarsFromRowColumn($row, $column)
    {
        $element = $row->getData();
        $columnId = $column->getId();
        assert($element instanceof \PKP\core\DataObject && !empty($columnId));
        /** @var EventLogEntry $element */
        switch ($columnId) {
            case 'date':
                return ['label' => $element instanceof EventLogEntry ? $element->getDateLogged() : $element->getDateSent()];
            case 'event':
                return ['label' => $element instanceof EventLogEntry ? $element->getTranslatedMessage(null, $this->_isCurrentUserAssignedAuthor) : $element->getPrefixedSubject()];
            case 'user':
                if ($element instanceof EventLogEntry) {
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
                                if ($reviewAssignment && $reviewAssignment->getReviewMethod() === ReviewAssignment::SUBMISSION_REVIEW_METHOD_OPEN) {
                                    $userName = $element->getUserFullName();
                                }
                            }
                        }

                        // Maybe anonymize files submitted by reviewers
                        if (isset($params['fileStage']) && $params['fileStage'] === SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT) {
                            assert(isset($params['fileId']) && isset($params['submissionId']));
                            $submissionFile = Repo::submissionFile()->get($params['id']);
                            if ($submissionFile && $submissionFile->getData('assocType') === Application::ASSOC_TYPE_REVIEW_ASSIGNMENT) {
                                $reviewAssignment = $reviewAssignmentDao->getById($submissionFile->getData('assocId'));
                                if (!$reviewAssignment || in_array($reviewAssignment->getReviewMethod(), [ReviewAssignment::SUBMISSION_REVIEW_METHOD_ANONYMOUS, ReviewAssignment::SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS])) {
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
