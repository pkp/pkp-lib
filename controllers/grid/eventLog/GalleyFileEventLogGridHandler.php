<?php

/**
 * @file controllers/grid/eventLog/GalleyFileEventLogGridHandler.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GalleyFileEventLogGridHandler
 *
 * @brief Grid handler presenting the galley files event log grid.
 */

namespace PKP\controllers\grid\eventLog;

use APP\core\Application;
use APP\facades\Repo;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\galley\Galley;
use PKP\controllers\grid\eventLog\SubmissionEventLogGridHandler;
use PKP\security\authorization\internal\RepresentationRequiredPolicy;
use PKP\security\authorization\PublicationAccessPolicy;
use PKP\security\authorization\WorkflowStageAccessPolicy;
use PKP\security\Role;

class GalleyFileEventLogGridHandler extends SubmissionEventLogGridHandler
{
    /** @var Galley The galley */
    protected $_galley;

    //
    // Getters/Setters
    //
    /**
     * Get the galley associated with this grid.
     *
     * @return Galley Galley
     */
    public function getGalley()
    {
        return $this->_galley;
    }

    /**
     * Set the galley
     *
     * @param Galley $galley
     */
    public function setGalley($galley)
    {
        $this->_galley = $galley;
    }

    //
    // Overridden methods from PKPHandler
    //
    /**
     * @see PKPHandler::authorize()
     *
     * Uses same authorization pattern as ArticleGalleyGridHandler
     *
     * @param PKPRequest $request
     * @param array $args
     * @param array $roleAssignments
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        // Use WorkflowStageAccessPolicy for production stage
        $this->addPolicy(new WorkflowStageAccessPolicy(
            $request,
            $args,
            $roleAssignments,
            'submissionId',
            (int) $args['stageId']
        ));

        // Require valid publication access
        $this->addPolicy(new PublicationAccessPolicy($request, $args, $roleAssignments));

        // Require valid representation (galley)
        $this->addPolicy(new RepresentationRequiredPolicy($request, $args));

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
        $this->setGalley($this->getAuthorizedContextObject(Application::ASSOC_TYPE_REPRESENTATION));
    }

    //
    // Overridden methods from GridHandler
    //
    /**
     * Get the arguments that will identify the data in the grid
     *
     * @return array
     */
    public function getRequestArgs()
    {
        $galley = $this->getGalley();

        return [
            'submissionId' => $this->_submission->getId(),
            'representationId' => $galley->getId(),
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
        $galley = $this->getGalley();

        // Get all submission galley files associated with this galley
        $galleyFiles = Repo::submissionFile()
            ->getCollector()
            ->filterByAssoc(
                PKPApplication::ASSOC_TYPE_REPRESENTATION,
                [$galley->getId()]
            )
            ->getMany();

        $fileIds = $galleyFiles->map(fn ($file) => $file->getId())->toArray();

        if (empty($fileIds)) {
            return [];
        }

        return Repo::eventLog()->getCollector()
            ->filterByAssoc(PKPApplication::ASSOC_TYPE_SUBMISSION_FILE, $fileIds)
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
