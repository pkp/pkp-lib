<?php

/**
 * @file controllers/modals/publish/PublishHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublishHandler
 * @ingroup controllers_modals_publish
 *
 * @brief A handler to load final publishing confirmation checks
 */

// Import the base Handler.

use APP\facades\Repo;
use APP\handler\Handler;
use APP\template\TemplateManager;

use PKP\security\authorization\PublicationAccessPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;

class PublishHandler extends Handler
{
    /** @var Submission */
    public $submission;

    /** @var Publication */
    public $publication;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_MANAGER, Role::ROLE_ID_ASSISTANT],
            ['publish']
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
        $this->submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        $this->publication = $this->getAuthorizedContextObject(ASSOC_TYPE_PUBLICATION);
        $this->setupTemplate($request);
    }

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));
        $this->addPolicy(new PublicationAccessPolicy($request, $args, $roleAssignments));
        return parent::authorize($request, $args, $roleAssignments);
    }


    //
    // Public handler methods
    //
    /**
     * Display a publishing confirmation form
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function publish($args, $request)
    {
        $templateMgr = TemplateManager::getManager($request);

        $submissionContext = $request->getContext();
        if (!$submissionContext || $submissionContext->getId() !== $this->submission->getData('contextId')) {
            $submissionContext = Services::get('context')->get($this->submission->getData('contextId'));
        }
        $primaryLocale = $submissionContext->getPrimaryLocale();
        $allowedLocales = $submissionContext->getSupportedSubmissionLocales();
        $errors = Repo::publication()->validatePublish($this->publication, $this->submission, $allowedLocales, $primaryLocale);

        $publicationApiUrl = $request->getDispatcher()->url($request, PKPApplication::ROUTE_API, $submissionContext->getPath(), 'submissions/' . $this->submission->getId() . '/publications/' . $this->publication->getId() . '/publish');

        $publishForm = new APP\components\forms\publication\PublishForm($publicationApiUrl, $this->publication, $submissionContext, $errors);

        $settingsData = [
            'components' => [
                FORM_PUBLISH => $publishForm->getConfig(),
            ],
        ];

        $templateMgr->assign('publishData', $settingsData);

        return $templateMgr->fetchJson('controllers/modals/publish/publish.tpl');
    }
}
