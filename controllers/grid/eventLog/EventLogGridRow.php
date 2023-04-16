<?php

/**
 * @file controllers/grid/eventLog/EventLogGridRow.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EventLogGridRow
 *
 * @ingroup controllers_grid_eventLog
 *
 * @brief EventLog grid row definition
 */

namespace PKP\controllers\grid\eventLog;

use APP\core\Application;
use APP\facades\Repo;
use PKP\controllers\api\file\linkAction\DownloadFileLinkAction;
use PKP\controllers\grid\eventLog\linkAction\EmailLinkAction;
use PKP\controllers\grid\GridRow;
use PKP\db\DAORegistry;
use PKP\log\EmailLogEntry;
use PKP\log\EventLogEntry;
use PKP\log\SubmissionFileEventLogEntry;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submissionFile\SubmissionFile;

class EventLogGridRow extends GridRow
{
    /** @var Submission */
    public $_submission;

    /** @var bool Is the current user assigned as an author to this submission */
    public $_isCurrentUserAssignedAuthor;

    /**
     * Constructor
     *
     * @param Submission $submission
     * @param bool $isCurrentUserAssignedAuthor Is the current user assigned
     *  as an author to this submission?
     */
    public function __construct($submission, $isCurrentUserAssignedAuthor)
    {
        $this->_submission = $submission;
        $this->_isCurrentUserAssignedAuthor = $isCurrentUserAssignedAuthor;
        parent::__construct();
    }

    //
    // Overridden methods from GridRow
    //
    /**
     * @copydoc GridRow::initialize()
     *
     * @param null|mixed $template
     */
    public function initialize($request, $template = null)
    {
        parent::initialize($request, $template);

        $logEntry = $this->getData(); // a Category object
        assert($logEntry != null && ($logEntry instanceof EventLogEntry || $logEntry instanceof EmailLogEntry));

        if ($logEntry instanceof EventLogEntry) {
            $params = $logEntry->getParams();

            switch ($logEntry->getEventType()) {
                case SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_REVISION_UPLOAD:
                case SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_UPLOAD:
                    $submissionFileId = $params['submissionFileId'];
                    $fileId = $params['fileId'];
                    $submissionFile = Repo::submissionFile()->get($submissionFileId);
                    if (!$submissionFile) {
                        break;
                    }
                    $filename = $params['originalFileName'] ?? $submissionFile->getLocalizedData('name');
                    if ($submissionFile) {
                        $anonymousAuthor = false;
                        $maybeAnonymousAuthor = $this->_isCurrentUserAssignedAuthor && $submissionFile->getData('fileStage') === SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT;
                        if ($maybeAnonymousAuthor && $submissionFile->getData('assocType') === Application::ASSOC_TYPE_REVIEW_ASSIGNMENT) {
                            $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
                            $reviewAssignment = $reviewAssignmentDao->getById($submissionFile->getData('assocId'));
                            if ($reviewAssignment && in_array($reviewAssignment->getReviewMethod(), [ReviewAssignment::SUBMISSION_REVIEW_METHOD_ANONYMOUS, ReviewAssignment::SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS])) {
                                $anonymousAuthor = true;
                            }
                        }
                        if (!$anonymousAuthor) {
                            $workflowStageId = Repo::submissionFile()->getWorkflowStageId($submissionFile);
                            // If a submission file is attached to a query that has been deleted, we cannot
                            // determine its stage. Don't present a download link in this case.
                            if ($workflowStageId || $submissionFile->getData('fileStage') != SubmissionFile::SUBMISSION_FILE_QUERY) {
                                $this->addAction(new DownloadFileLinkAction($request, $submissionFile, $workflowStageId, __('common.download'), $fileId, $filename));
                            }
                        }
                    }
                    break;
            }
        } elseif ($logEntry instanceof EmailLogEntry) {
            $this->addAction(
                new EmailLinkAction(
                    $request,
                    __('submission.event.viewEmail'),
                    [
                        'submissionId' => $logEntry->getAssocId(),
                        'emailLogEntryId' => $logEntry->getId(),
                    ]
                )
            );
        }
    }
}
