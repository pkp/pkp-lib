<?php

/**
 * @file controllers/modals/documentLibrary/DocumentLibraryHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DocumentLibraryHandler
 * @ingroup controllers_modals_documentLibrary
 *
 * @brief Submission document library modal handler.
 */

use APP\handler\Handler;
use APP\template\TemplateManager;

use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;

class DocumentLibraryHandler extends Handler
{
    /** @var Submission */
    public $_submission;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_AUTHOR, Role::ROLE_ID_ASSISTANT],
            ['documentLibrary']
        );
    }


    //
    // Overridden methods from Handler
    //
    /**
     * @copydoc PKPHandler::initialize()
     */
    public function initialize($request)
    {
        parent::initialize($request);

        $this->_submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        $this->setupTemplate($request);
    }

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));
        return parent::authorize($request, $args, $roleAssignments);
    }


    //
    // Getters and Setters
    //
    /**
     * Get the Submission
     *
     * @return Submission
     */
    public function getSubmission()
    {
        return $this->_submission;
    }

    //
    // Public handler methods
    //
    /**
     * Display a list of the review form elements within a review form.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function documentLibrary($args, $request)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('submission', $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION));
        return $templateMgr->fetchJson('controllers/modals/documentLibrary/documentLibrary.tpl');
    }
}
