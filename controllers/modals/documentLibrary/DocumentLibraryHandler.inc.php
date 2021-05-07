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

class DocumentLibraryHandler extends Handler
{
    /** The submission **/
    public $_submission;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [ROLE_ID_SUB_EDITOR, ROLE_ID_MANAGER, ROLE_ID_AUTHOR, ROLE_ID_ASSISTANT],
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
     * @param $args array
     * @param $request PKPRequest
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
