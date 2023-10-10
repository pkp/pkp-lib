<?php

/**
 * @file controllers/grid/eventLog/SubmissionFileEventLogGridHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileEventLogGridHandler
 *
 * @ingroup controllers_grid_eventLog
 *
 * @brief Grid handler presenting the submission file event log grid.
 */

namespace PKP\controllers\grid\eventLog;

use APP\core\Application;
use APP\facades\Repo;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\security\authorization\SubmissionFileAccessPolicy;
use PKP\security\Role;
use PKP\submissionFile\SubmissionFile;

class SubmissionFileEventLogGridHandler extends SubmissionEventLogGridHandler
{
    /** @var SubmissionFile SubmissionFile */
    public $_submissionFile;

    //
    // Getters/Setters
    //
    /**
     * Get the submission file associated with this grid.
     *
     * @return SubmissionFile
     */
    public function getSubmissionFile()
    {
        return $this->_submissionFile;
    }

    /**
     * Set the submission file
     *
     * @param SubmissionFile $submissionFile
     */
    public function setSubmissionFile($submissionFile)
    {
        $this->_submissionFile = $submissionFile;
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
        $this->addPolicy(new SubmissionFileAccessPolicy($request, $args, $roleAssignments, SubmissionFileAccessPolicy::SUBMISSION_FILE_ACCESS_READ, (int) $args['submissionFileId']));
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Configure the grid
     *
     * @see SubmissionEventLogGridHandler::initialize
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        // Retrieve the authorized monograph.
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $submissionFile = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION_FILE);
        $this->setSubmissionFile($submissionFile);
    }


    //
    // Overridden methods from GridHandler
    //
    /**
     * Get the arguments that will identify the data in the grid
     * In this case, the monograph.
     *
     * @return array
     */
    public function getRequestArgs()
    {
        $submissionFile = $this->getSubmissionFile();

        return [
            'submissionId' => $submissionFile->getData('submissionId'),
            'submissionFileId' => $submissionFile->getId(),
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
        return Repo::eventLog()->getCollector()
            ->filterByAssoc(PKPApplication::ASSOC_TYPE_SUBMISSION_FILE, [$this->getSubmissionFile()->getId()])
            ->getMany()
            ->toArray();
    }

    /**
     * @copydoc GridHandler::getFilterForm()
     *
     * @return string Filter template.
     */
    protected function getFilterForm()
    {
        // If the user only has an author role, do not permit access
        // to earlier stages.
        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        if (array_intersect([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT], $userRoles)) {
            return 'controllers/grid/eventLog/eventLogGridFilter.tpl';
        }
        return parent::getFilterForm();
    }

    /**
     * @copydoc GridHandler::getFilterSelectionData()
     */
    public function getFilterSelectionData($request)
    {
        return ['allEvents' => $request->getUserVar('allEvents') ? true : false];
    }
}
