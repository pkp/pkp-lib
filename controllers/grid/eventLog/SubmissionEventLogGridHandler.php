<?php

/**
 * @file controllers/grid/eventLog/SubmissionEventLogGridHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionEventLogGridHandler
 *
 * @ingroup controllers_grid_eventLog
 *
 * @brief Grid handler presenting the submission event log grid.
 */

namespace PKP\controllers\grid\eventLog;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use PKP\controllers\grid\DateGridCellProvider;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\core\PKPString;
use PKP\db\DAORegistry;
use PKP\log\EmailLogEntry;
use PKP\log\event\EventLogEntry;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\security\authorization\internal\UserAccessibleWorkflowStageRequiredPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\SubmissionComment;
use PKP\submission\SubmissionCommentDAO;

class SubmissionEventLogGridHandler extends GridHandler
{
    /** @var Submission */
    public $_submission;

    /** @var int The current workflow stage */
    public $_stageId;

    /** @var bool Is the current user assigned as an author to this submission */
    public $_isCurrentUserAssignedAuthor;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR],
            ['fetchGrid', 'fetchRow', 'viewEmail']
        );
    }


    //
    // Getters/Setters
    //
    /**
     * Get the submission associated with this grid.
     *
     * @return Submission
     */
    public function getSubmission()
    {
        return $this->_submission;
    }

    /**
     * Set the Submission
     *
     * @param Submission $submission
     */
    public function setSubmission($submission)
    {
        $this->_submission = $submission;
    }


    //
    // Overridden methods from PKPHandler
    //
    /**
     * @see PKPHandler::authorize()
     *
     * @param PKPRequest $request
     * @param array $args
     * @param array $roleAssignments
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));

        $this->addPolicy(new UserAccessibleWorkflowStageRequiredPolicy($request, PKPApplication::WORKFLOW_TYPE_EDITORIAL));

        $success = parent::authorize($request, $args, $roleAssignments);

        // Prevent authors from accessing review details, even if they are also
        // assigned as an editor, sub-editor or assistant.
        $userAssignedRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);
        $this->_isCurrentUserAssignedAuthor = false;
        foreach ($userAssignedRoles as $stageId => $roles) {
            if (in_array(Role::ROLE_ID_AUTHOR, $roles)) {
                $this->_isCurrentUserAssignedAuthor = true;
                break;
            }
        }

        return $success;
    }

    /**
     * @copydoc GridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        // Retrieve the authorized monograph.
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $this->setSubmission($submission);

        $this->_stageId = (int) ($args['stageId'] ?? null);

        // Columns
        $cellProvider = new EventLogGridCellProvider($this->_isCurrentUserAssignedAuthor);
        $this->addColumn(
            new GridColumn(
                'date',
                'common.date',
                null,
                null,
                new DateGridCellProvider(
                    $cellProvider,
                    Application::get()->getRequest()->getContext()->getLocalizedDateFormatShort()
                )
            )
        );
        $this->addColumn(
            new GridColumn(
                'user',
                'common.user',
                null,
                null,
                $cellProvider
            )
        );
        $this->addColumn(
            new GridColumn(
                'event',
                'common.event',
                null,
                null,
                $cellProvider,
                ['width' => 60]
            )
        );
    }


    //
    // Overridden methods from GridHandler
    //
    /**
     * @see GridHandler::getRowInstance()
     *
     * @return EventLogGridRow
     */
    protected function getRowInstance()
    {
        return new EventLogGridRow($this->getSubmission(), $this->_isCurrentUserAssignedAuthor);
    }

    /**
     * Get the arguments that will identify the data in the grid
     * In this case, the monograph.
     *
     * @return array
     */
    public function getRequestArgs()
    {
        $submission = $this->getSubmission();

        return [
            'submissionId' => $submission->getId(),
            'stageId' => $this->_stageId,
        ];
    }

    /**
     * @copydoc GridHandler::loadData
     *
     * @param null|mixed $filter
     */
    protected function loadData($request, $filter = null)
    {
        $submission = $this->getSubmission();

        $eventLogEntries = Repo::eventLog()->getCollector()
            ->filterByAssoc(PKPApplication::ASSOC_TYPE_SUBMISSION, [$submission->getId()])
            ->getMany();

        $emailLogEntries = EmailLogEntry::withAssocId($submission->getId())
            ->withAssocType(Application::ASSOC_TYPE_SUBMISSION)->get();

        $reviewLogEntries = $this->getReviewChangeEntries($submission);
        $entries = array_merge($eventLogEntries->toArray(), $reviewLogEntries, $emailLogEntries->all());

        // Sort the merged data by date, most recent first
        usort($entries, function ($a, $b) {
            $aDate = $a instanceof EventLogEntry ? $a->getDateLogged() : $a->dateSent;
            $bDate = $b instanceof EventLogEntry ? $b->getDateLogged() : $b->dateSent;

            if ($aDate == $bDate) {
                return 0;
            }

            return $aDate < $bDate ? 1 : -1;
        });

        return $entries;
    }

    /**
     * Get the contents of the email
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function viewEmail($args, $request)
    {
        $emailLogEntry = EmailLogEntry::find((int) $args['emailLogEntryId']);
        return new JSONMessage(true, $this->_formatEmail($emailLogEntry));
    }

    /**
     * Format the contents of the email
     *
     *
     * @return string Formatted email
     */
    public function _formatEmail(EmailLogEntry $emailLogEntry)
    {
        $text = [];
        $text[] = __('email.from') . ': ' . htmlspecialchars($emailLogEntry->fromAddress);
        $text[] = __('email.to') . ': ' . htmlspecialchars($emailLogEntry->recipients);
        if ($emailLogEntry->ccRecipients) {
            $text[] = __('email.cc') . ': ' . htmlspecialchars($emailLogEntry->ccRecipients);
        }
        if ($emailLogEntry->bccRecipients) {
            $text[] = __('email.bcc') . ': ' . htmlspecialchars($emailLogEntry->bccRecipients);
        }
        $text[] = __('email.subject') . ': ' . htmlspecialchars($emailLogEntry->subject);

        return
            '<div class="pkp_workflow_email_log_view">'
            . nl2br(join(PHP_EOL, $text)) . '<br><br>'
            . PKPString::stripUnsafeHtml($emailLogEntry->body)
            . '</div>';
    }

    /**
     * Get the logs related to the editing of a review.
     */
    protected function getReviewChangeEntries(Submission $submission): array
    {
        $reviewAssignments = Repo::reviewAssignment()->getCollector()
            ->filterBySubmissionIds([$submission->getId()])
            ->getMany();

        $reviewsWithFormIds = $reviewAssignments->filter(function (ReviewAssignment $reviewAssignment) {
            return !!$reviewAssignment->getReviewFormId();
        })
            ->map(function (ReviewAssignment $reviewAssignment) {
                return $reviewAssignment->getId();
            })->toArray();

        $reviewsWithCommentsIds = $reviewAssignments->filter(function (ReviewAssignment $reviewAssignment) {
            return !$reviewAssignment->getReviewFormId();
        })
            ->map(function (ReviewAssignment $reviewAssignment) {
                return $reviewAssignment->getId();
            })->toArray();

        /** @var SubmissionCommentDAO $submissionCommentDao */
        $submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO');
        $reviewComments = $submissionCommentDao->getSubmissionComments($submission->getId(), SubmissionComment::COMMENT_TYPE_PEER_REVIEW);
        $reviewCommentIds = [];

        /** @var SubmissionComment $reviewComment */
        while ($reviewComment = $reviewComments->next()) {
            if ($reviewComment->getViewable()) {
                $reviewCommentIds[] = $reviewComment->getId();
            }
        }

        $commentLogEntries = $reviewCommentIds ? Repo::eventLog()->getCollector()
            ->filterByAssoc(PKPApplication::ASSOC_TYPE_SUBMISSION_REVIEW_COMMENT, $reviewCommentIds)
            ->filterByEventType(PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_REVIEWER_COMMENTS_MODIFIED)
            ->getMany()
            ->toArray() : [];

        $formResponseLogEntries = $reviewsWithFormIds ? Repo::eventLog()->getCollector()
            ->filterByAssoc(PKPApplication::ASSOC_TYPE_REVIEW_ASSIGNMENT, $reviewsWithFormIds)
            ->filterByEventType(PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_REVIEWER_FORM_RESPONSE_MODIFIED)
            ->getMany()
            ->toArray() : [];

        $recommendationLogEntries = $reviewsWithCommentsIds ? Repo::eventLog()->getCollector()
            ->filterByAssoc(PKPApplication::ASSOC_TYPE_REVIEW_ASSIGNMENT, array_merge($reviewsWithCommentsIds, $reviewsWithFormIds))
            ->filterByEventType(PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_REVIEWER_RECOMMENDATION_MODIFIED)
            ->getMany()
            ->toArray() : [];

        return array_merge(
            $commentLogEntries,
            $formResponseLogEntries,
            $recommendationLogEntries
        );
    }
}
