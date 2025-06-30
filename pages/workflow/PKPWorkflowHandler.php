<?php

/**
 * @file pages/workflow/PKPWorkflowHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPWorkflowHandler
 *
 * @ingroup pages_reviewer
 *
 * @brief Handle requests for the submission workflow.
 */

namespace PKP\pages\workflow;

use APP\components\forms\publication\PublishForm;
use APP\core\Application;
use APP\core\PageRouter;
use APP\core\Request;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\publication\Publication;
use APP\submission\Submission;
use APP\template\TemplateManager;
use Exception;
use Illuminate\Support\Enumerable;
use PKP\components\forms\FormComponent;
use PKP\components\forms\publication\PKPCitationsForm;
use PKP\components\forms\publication\PKPMetadataForm;
use PKP\components\forms\publication\PKPPublicationLicenseForm;
use PKP\components\forms\publication\TitleAbstractForm;
use PKP\components\forms\submission\ChangeSubmissionLanguageMetadataForm;
use PKP\components\listPanels\ContributorsListPanel;
use PKP\components\PublicationSectionJats;
use PKP\config\Config;
use PKP\context\Context;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\decision\Decision;
use PKP\facades\Locale;
use PKP\notification\Notification;
use PKP\plugins\PluginRegistry;
use PKP\security\authorization\internal\SubmissionCompletePolicy;
use PKP\security\authorization\internal\SubmissionRequiredPolicy;
use PKP\security\authorization\internal\UserAccessibleWorkflowStageRequiredPolicy;
use PKP\security\authorization\WorkflowStageAccessPolicy;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\submission\genre\Genre;
use PKP\submission\PKPSubmission;
use PKP\submission\reviewRound\ReviewRoundDAO;
use PKP\user\User;
use PKP\userGroup\UserGroup;
use PKP\workflow\WorkflowStageDAO;

abstract class PKPWorkflowHandler extends Handler
{
    /** @copydoc PKPHandler::_isBackendPage */
    public $_isBackendPage = true;

    //
    // Implement template methods from PKPHandler
    //
    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        /** @var PageRouter */
        $router = $request->getRouter();
        $operation = $router->getRequestedOp($request);

        if ($operation == 'access') {
            // Authorize requested submission.
            $this->addPolicy(new SubmissionRequiredPolicy($request, $args, 'submissionId'));

            // This policy will deny access if user has no accessible workflow stage.
            // Otherwise it will build an authorized object with all accessible
            // workflow stages and authorize user operation access.
            $this->addPolicy(new UserAccessibleWorkflowStageRequiredPolicy($request, PKPApplication::WORKFLOW_TYPE_EDITORIAL));

            $this->markRoleAssignmentsChecked();
        } else {
            $this->addPolicy(new SubmissionCompletePolicy($request, $args, 'submissionId'));
            $this->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $this->identifyStageId($request, $args), PKPApplication::WORKFLOW_TYPE_EDITORIAL));
        }

        return parent::authorize($request, $args, $roleAssignments);
    }


    //
    // Public handler methods
    //
    /**
     * Redirect user to a submission workflow.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function access($args, $request)
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $router = $request->getRouter();
        return $request->redirectUrl($router->url($request, null, 'dashboard', 'editorial', null, ['workflowSubmissionId' => $submission->getId()]));
    }

    /**
     * Show the workflow stage, with the stage path as an #anchor.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function index($args, $request)
    {

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $router = $request->getRouter();
        return $request->redirectUrl($router->url($request, null, 'dashboard', 'editorial', null, ['workflowSubmissionId' => $submission->getId()]));
    }

    /**
     * Show the submission stage.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function submission($args, $request)
    {
        $this->_redirectToIndex($args, $request);
    }

    /**
     * Show the external review stage.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function externalReview($args, $request)
    {
        $this->_redirectToIndex($args, $request);
    }

    /**
     * Show the editorial stage
     *
     * @param PKPRequest $request
     * @param array $args
     */
    public function editorial($args, $request)
    {
        $this->_redirectToIndex($args, $request);
    }

    /**
     * Show the production stage
     *
     * @param PKPRequest $request
     * @param array $args
     */
    public function production($args, $request)
    {
        $this->_redirectToIndex($args, $request);
    }

    /**
     * Redirect all old stage paths to index
     *
     * @param array $args
     * @param PKPRequest $request
     */
    protected function _redirectToIndex($args, $request)
    {
        // Translate the operation to a workflow stage identifier.
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $router = $request->getRouter();
        $workflowPath = $router->getRequestedOp($request);
        $stageId = WorkflowStageDAO::getIdFromPath($workflowPath);
        $request->redirectUrl($router->url($request, null, 'workflow', 'index', [$submission->getId(), $stageId]));
    }

    /**
     * Placeholder method to be overridden by apps in order to add
     * app-specific data to the template
     *
     * @param Request $request
     */
    public function setupIndex($request)
    {
    }

    //
    // Protected helper methods
    //

    /**
     * Translate the requested operation to a stage id.
     *
     * @param Request $request
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

    /**
     * Get the form configuration data with the correct
     * locale settings based on the publication's locale
     *
     * Uses the publication locale as the primary and
     * visible locale, and puts that locale first in the
     * list of supported locales.
     *
     * Call this instead of $form->getConfig() to display
     * a form with the correct publication locales
     */
    protected function getLocalizedForm(FormComponent $form, string $submissionLocale, array $locales): array
    {
        $config = $form->getConfig();

        $config['primaryLocale'] = $submissionLocale;
        $config['visibleLocales'] = [$submissionLocale];
        $config['supportedFormLocales'] = collect($locales)
            ->sortBy([fn (array $a, array $b) => $b['key'] === $submissionLocale ? 1 : -1])
            ->values()
            ->toArray();

        return $config;
    }

    /**
     * Get a label for a recommendation decision type
     */
    protected function getRecommendationLabel(int $decision): string
    {
        $decisionType = Repo::decision()->getDecisionType($decision);
        if (!$decisionType || !method_exists($decisionType, 'getRecommendationLabel')) {
            throw new Exception('Could not find label for unknown recommendation type.');
        }
        return $decisionType->getRecommendationLabel();
    }

    /**
     * Get the contributor list panel
     */
    protected function getContributorsListPanel(Submission $submission, Context $context, array $locales, array $authorItems, bool $canEditPublication): ContributorsListPanel
    {
        return new ContributorsListPanel(
            'contributors',
            __('publication.contributors'),
            $submission,
            $context,
            $locales,
            $authorItems,
            $canEditPublication
        );
    }

    /**
     * Get the contributor list panel
     */
    protected function getJatsPanel(Submission $submission, Context $context, bool $canEditPublication, Publication $publication): PublicationSectionJats
    {
        return new PublicationSectionJats(
            'jats',
            __('publication.jats'),
            $submission,
            $context,
            $canEditPublication,
            $publication
        );
    }

    //
    // Abstract protected methods.
    //
    /**
    * Return the editor assignment notification type based on stage id.
    *
    * @param int $stageId
    *
    * @return int
    */
    abstract protected function getEditorAssignmentNotificationTypeByStageId($stageId);

    /**
     * Get the form for entering the title/abstract details
     */
    abstract protected function getTitleAbstractForm(string $latestPublicationApiUrl, array $locales, Publication $latestPublication, Context $context): TitleAbstractForm;
}
