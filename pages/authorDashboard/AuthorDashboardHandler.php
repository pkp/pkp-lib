<?php

/**
 * @file pages/authorDashboard/AuthorDashboardHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorDashboardHandler
 *
 * @ingroup pages_authorDashboard
 *
 * @brief Handle requests for the author dashboard.
 */

namespace APP\pages\authorDashboard;

use APP\core\Application;
use APP\core\PageRouter;
use APP\core\Services;
use APP\facades\Repo;
use APP\publication\Publication;
use APP\template\TemplateManager;
use PKP\components\forms\publication\TitleAbstractForm;
use PKP\context\Context;
use PKP\core\PKPApplication;
use PKP\pages\authorDashboard\PKPAuthorDashboardHandler;
use PKP\workflow\WorkflowStageDAO;

class AuthorDashboardHandler extends PKPAuthorDashboardHandler
{
    /**
     * Setup variables for the template
     *
     * @param \APP\core\Request $request
     */
    public function setupTemplate($request)
    {
        parent::setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        $submissionContext = $request->getContext();
        if ($submission->getContextId() !== $submissionContext->getId()) {
            $submissionContext = Services::get('context')->get($submission->getContextId());
        }

        $locales = $submissionContext->getSupportedFormLocaleNames();
        $locales = array_map(fn (string $locale, string $name) => ['key' => $locale, 'label' => $name], array_keys($locales), $locales);

        $latestPublication = $submission->getLatestPublication();
        $relatePublicationApiUrl = $request->getDispatcher()->url($request, PKPApplication::ROUTE_API, $submissionContext->getPath(), 'submissions/' . $submission->getId() . '/publications/' . $latestPublication->getId()) . '/relate';

        $publishUrl = $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_COMPONENT,
            null,
            'modals.publish.OPSPublishHandler',
            'publish',
            null,
            [
                'submissionId' => $submission->getId(),
                'publicationId' => '__publicationId__',
            ]
        );

        $relationForm = new \APP\components\forms\publication\RelationForm($relatePublicationApiUrl, $latestPublication);

        // Import constants
        class_exists(\APP\components\forms\publication\RelationForm::class); // Force define of FORM_ID_RELATION
        $templateMgr->setConstants([
            'FORM_ID_RELATION' => FORM_ID_RELATION,
            'FORM_PUBLISH' => FORM_PUBLISH,
        ]);

        $components = $templateMgr->getState('components');
        $components[FORM_ID_RELATION] = $relationForm->getConfig();

        $publicationFormIds = $templateMgr->getState('publicationFormIds');
        $publicationFormIds[] = FORM_PUBLISH;

        $templateMgr->setState([
            'components' => $components,
            'publicationFormIds' => $publicationFormIds,
            'publishLabel' => __('publication.publish'),
            'publishUrl' => $publishUrl,
            'unpublishConfirmLabel' => __('publication.unpublish.confirm'),
            'unpublishLabel' => __('publication.unpublish'),
        ]);

        // If current user can publish show publish buttons
        $canPublish = Repo::publication()->canCurrentUserPublish($submission->getId()) ? true : false;
        $templateMgr->assign('canPublish', $canPublish);
    }

    /**
     * @copydoc PKPAuthorDashboardHandler::_getRepresentationsGridUrl()
     */
    protected function _getRepresentationsGridUrl($request, $submission)
    {
        return $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_COMPONENT,
            null,
            'grid.preprintGalleys.PreprintGalleyGridHandler',
            'fetchGrid',
            null,
            [
                'submissionId' => $submission->getId(),
                'publicationId' => '__publicationId__',
            ]
        );
    }

    /**
     * Translate the requested operation to a stage id.
     *
     * @param \APP\core\Request $request
     * @param array $args
     *
     * @return int One of the WORKFLOW_STAGE_* constants.
     */
    protected function identifyStageId($request, $args)
    {
        if ($stageId = $request->getUserVar('stageId')) {
            return (int) $stageId;
        }
        // Maintain the old check for previous path urls
        /** @var PageRouter */
        $router = $request->getRouter();
        $workflowPath = $router->getRequestedOp($request);
        $stageId = WorkflowStageDAO::getIdFromPath($workflowPath);
        if ($stageId) {
            return $stageId;
        }
        // Finally, retrieve the requested operation, if the stage id is
        // passed in via an argument in the URL, like index/submissionId/stageId
        $stageId = $args[1];
        // Translate the operation to a workflow stage identifier.
        assert(WorkflowStageDAO::getPathFromId($stageId) !== null);
        return $stageId;
    }

    protected function getTitleAbstractForm(string $latestPublicationApiUrl, array $locales, Publication $latestPublication, Context $context): TitleAbstractForm
    {
        $section = Repo::section()->get($latestPublication->getData('sectionId'), $context->getId());

        return new TitleAbstractForm(
            $latestPublicationApiUrl,
            $locales,
            $latestPublication,
            (int) $section->getData('wordCount'),
            !$section->getData('abstractsNotRequired')
        );
    }
}
