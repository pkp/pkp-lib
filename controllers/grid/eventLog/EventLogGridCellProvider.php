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
use PKP\log\EmailLogEntry;
use PKP\log\event\EventLogEntry;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\submission\reviewAssignment\ReviewAssignment;
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
        assert(($element instanceof \PKP\core\DataObject || $element instanceof EmailLogEntry) && !empty($columnId) );
        /** @var EventLogEntry $element */
        switch ($columnId) {
            case 'date':
                return ['label' => $element instanceof EventLogEntry ? $element->getDateLogged() : $element->dateSent];
            case 'event':
                return ['label' => $element instanceof EventLogEntry ? $element->getTranslatedMessage(null, $this->_isCurrentUserAssignedAuthor) : $element->prefixedSubject];
            case 'user':
                if ($element instanceof EventLogEntry) {
                    $userName = $element->getUserFullName();

                    // Anonymize reviewer details where necessary
                    if ($this->_isCurrentUserAssignedAuthor) {

                        // Maybe anonymize reviewer log entries
                        $reviewerLogTypes = [
                            PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_ACCEPT,
                            PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_DECLINE,
                            PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_UNCONSIDERED,
                        ];
                        if (in_array($element->getEventType(), $reviewerLogTypes)) {
                            $userName = __('editor.review.anonymousReviewer');
                            if ($reviewAssignmentId = $element->getData('reviewAssignmentId')) {
                                $reviewAssignment = Repo::reviewAssignment()->get($reviewAssignmentId);
                                if ($reviewAssignment && $reviewAssignment->getReviewMethod() === ReviewAssignment::SUBMISSION_REVIEW_METHOD_OPEN) {
                                    $userName = $element->getUserFullName();
                                }
                            }
                        }

                        // Maybe anonymize files submitted by reviewers
                        $fileStage = $element->getData('fileStage');
                        if ($fileStage && $fileStage === SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT) {
                            $submissionFileId = $element->getData('submissionFileId');
                            assert($element->getData('fileId') && $element->getData('submissionId') && $submissionFileId);
                            $submissionFile = Repo::submissionFile()->get($submissionFileId);
                            if ($submissionFile && $submissionFile->getData('assocType') === Application::ASSOC_TYPE_REVIEW_ASSIGNMENT) {
                                $reviewAssignment = Repo::reviewAssignment()->get($submissionFile->getData('assocId'));
                                if (!$reviewAssignment || in_array($reviewAssignment->getReviewMethod(), [ReviewAssignment::SUBMISSION_REVIEW_METHOD_ANONYMOUS, ReviewAssignment::SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS])) {
                                    $userName = __('editor.review.anonymousReviewer');
                                }
                            }
                        }
                    }
                } else {
                    $userName = $element->senderFullName;
                }
                return ['label' => $userName];
            default:
                assert(false);
        }
    }
}
