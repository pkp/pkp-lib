<?php

/**
 * @file controllers/grid/eventLog/SubmissionEventLogGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionEventLogGridHandler
 * @ingroup controllers_grid_eventLog
 *
 * @brief Grid handler presenting the submission event log grid.
 */

// Other classes used by this grid
import('lib.pkp.controllers.grid.eventLog.EventLogGridRow');
import('lib.pkp.controllers.grid.eventLog.EventLogGridCellProvider');

use PKP\controllers\grid\DateGridCellProvider;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\core\JSONMessage;
use PKP\log\EmailLogEntry;
use PKP\log\EventLogEntry;
use PKP\security\authorization\internal\UserAccessibleWorkflowStageRequiredPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;

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
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR],
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
        $userAssignedRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);
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
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
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
                    \Application::get()->getRequest()->getContext()->getLocalizedDateFormatShort()
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
        $submissionEventLogDao = DAORegistry::getDAO('SubmissionEventLogDAO'); /** @var SubmissionEventLogDAO $submissionEventLogDao */
        $submissionEmailLogDao = DAORegistry::getDAO('SubmissionEmailLogDAO'); /** @var SubmissionEmailLogDAO $submissionEmailLogDao */

        $submission = $this->getSubmission();

        $eventLogEntries = $submissionEventLogDao->getBySubmissionId($submission->getId());
        $emailLogEntries = $submissionEmailLogDao->getBySubmissionId($submission->getId());

        $entries = array_merge($eventLogEntries->toArray(), $emailLogEntries->toArray());

        // Sort the merged data by date, most recent first
        usort($entries, function ($a, $b) {
            $aDate = $a instanceof EventLogEntry ? $a->getDateLogged() : $a->getDateSent();
            $bDate = $b instanceof EventLogEntry ? $b->getDateLogged() : $b->getDateSent();

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
        $submissionEmailLogDao = DAORegistry::getDAO('SubmissionEmailLogDAO'); /** @var SubmissionEmailLogDAO $submissionEmailLogDao */
        $emailLogEntry = $submissionEmailLogDao->getById((int) $args['emailLogEntryId']);
        return new JSONMessage(true, $this->_formatEmail($emailLogEntry));
    }

    /**
     * Format the contents of the email
     *
     * @param EmailLogEntry $emailLogEntry
     *
     * @return string Formatted email
     */
    public function _formatEmail($emailLogEntry)
    {
        assert($emailLogEntry instanceof EmailLogEntry);

        $text = [];
        $text[] = __('email.from') . ': ' . htmlspecialchars($emailLogEntry->getFrom());
        $text[] = __('email.to') . ': ' . htmlspecialchars($emailLogEntry->getRecipients());
        $text[] = __('email.subject') . ': ' . htmlspecialchars($emailLogEntry->getSubject());
        $text[] = $emailLogEntry->getBody();

        return nl2br(PKPString::stripUnsafeHtml(implode(PHP_EOL . PHP_EOL, $text)));
    }
}
