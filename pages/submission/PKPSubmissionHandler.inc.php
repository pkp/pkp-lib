<?php

/**
 * @file pages/submission/PKPSubmissionHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionHandler
 * @ingroup pages_submission
 *
 * @brief Base handler for submission requests.
 */

use APP\facades\Repo;
use APP\handler\Handler;
use APP\submission\Submission;

use APP\template\TemplateManager;
use PKP\core\JSONMessage;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\authorization\UserRequiredPolicy;

abstract class PKPSubmissionHandler extends Handler
{
    /** @copydoc PKPHandler::_isBackendPage */
    public $_isBackendPage = true;

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        // The policy for the submission handler depends on the
        // step currently requested.
        $step = isset($args[0]) ? (int) $args[0] : 1;
        if ($step < 1 || $step > $this->getStepCount()) {
            return false;
        }

        // Do we have a submission present in the request?
        $submissionId = (int)$request->getUserVar('submissionId');

        // Are we in step one without a submission present?
        if ($step === 1 && $submissionId === 0) {
            // Authorize submission creation. Author role not required.
            $this->addPolicy(new UserRequiredPolicy($request));
            $this->markRoleAssignmentsChecked();
        } else {
            // Authorize editing of incomplete submissions.
            $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments, 'submissionId'));
        }

        // Do policy checking.
        if (!parent::authorize($request, $args, $roleAssignments)) {
            return false;
        }

        // Execute additional checking of the step.
        // NB: Move this to its own policy for reuse when required in other places.
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

        // Permit if there is no submission set, but request is for initial step.
        if (!($submission instanceof Submission) && $step == 1) {
            return true;
        }

        // In all other cases we expect an authorized submission due to
        // the submission access policy above.
        assert($submission instanceof Submission);

        // Deny if submission is complete (==0 means complete) and at
        // any step other than the "complete" step (the last one)
        if ($submission->getSubmissionProgress() == 0 && $step != $this->getStepCount()) {
            return false;
        }

        // Deny if trying to access a step greater than the current progress
        if ($submission->getSubmissionProgress() != 0 && $step > $submission->getSubmissionProgress()) {
            return false;
        }

        return true;
    }


    //
    // Public Handler Methods
    //
    /**
     * Redirect to the new submission wizard by default.
     *
     * @param array $args
     * @param Request $request
     */
    public function index($args, $request)
    {
        $request->redirect(null, null, 'wizard');
    }

    /**
     * Display the tab set for the submission wizard.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function wizard($args, $request)
    {
        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);
        $step = isset($args[0]) ? (int) $args[0] : 1;
        $templateMgr->assign('step', $step);

        $templateMgr->assign('sectionId', (int) $request->getUserVar('sectionId')); // to add a sectionId parameter to tab links in template

        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        if ($submission) {
            $templateMgr->assign('submissionId', $submission->getId());
            $templateMgr->assign('submissionProgress', (int) $submission->getSubmissionProgress());
        } else {
            $templateMgr->assign('submissionProgress', 1);
        }
        $templateMgr->assign([
            'pageTitle' => __('submission.submit.title'),
        ]);
        $templateMgr->display('submission/form/index.tpl');
    }

    /**
     * Display a step for the submission wizard.
     * Displays submission index page if a valid step is not specified.
     *
     * @param array $args
     * @param Request $request
     *
     * @return JSONMessage JSON object
     */
    public function step($args, $request)
    {
        $step = isset($args[0]) ? (int) $args[0] : 1;

        $context = $request->getContext();
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

        $this->setupTemplate($request);

        if ($step < $this->getStepCount()) {
            $formClass = "\\APP\\submission\\form\\SubmissionSubmitStep{$step}Form";
            $submitForm = new $formClass($context, $submission);
            $submitForm->initData();
            return new JSONMessage(true, $submitForm->fetch($request));
        } elseif ($step == $this->getStepCount()) {
            $templateMgr = TemplateManager::getManager($request);
            $templateMgr->assign('context', $context);

            // Retrieve the correct url for author review his submission.
            $reviewSubmissionUrl = Repo::submission()->getWorkflowUrlByUserRoles($submission);
            $router = $request->getRouter();
            $dispatcher = $router->getDispatcher();

            $templateMgr->assign([
                'reviewSubmissionUrl' => $reviewSubmissionUrl,
                'submissionId' => $submission->getId(),
                'submitStep' => $step,
                'submissionProgress' => $submission->getSubmissionProgress(),
            ]);

            return new JSONMessage(true, $templateMgr->fetch('submission/form/complete.tpl'));
        }
    }

    /**
     * Save a submission step.
     *
     * @param array $args first parameter is the step being saved
     * @param Request $request
     *
     * @return JSONMessage JSON object
     */
    public function saveStep($args, $request)
    {
        $step = isset($args[0]) ? (int) $args[0] : 1;

        $router = $request->getRouter();
        $context = $router->getContext($request);
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

        $this->setupTemplate($request);

        $formClass = "\\APP\submission\\form\\SubmissionSubmitStep{$step}Form";
        $submitForm = new $formClass($context, $submission);
        $submitForm->readInputData();

        if (!HookRegistry::call('SubmissionHandler::saveSubmit', [$step, &$submission, &$submitForm])) {
            if ($submitForm->validate()) {
                $submissionId = $submitForm->execute();
                if (!$submission) {
                    return $request->redirectUrlJson($router->url($request, null, null, 'wizard', $step + 1, ['submissionId' => $submissionId], 'step-2'));
                }
                $json = new JSONMessage(true);
                $json->setEvent('setStep', max($step + 1, $submission->getSubmissionProgress()));
                return $json;
            } else {
                // Provide entered tagit fields values
                $tagitKeywords = $submitForm->getData('keywords');
                if (is_array($tagitKeywords)) {
                    $tagitFieldNames = $submitForm->metadataForm->getTagitFieldNames();
                    $locales = array_keys($submitForm->supportedLocales);
                    $formTagitData = [];
                    foreach ($tagitFieldNames as $tagitFieldName) {
                        foreach ($locales as $locale) {
                            $formTagitData[$locale] = array_key_exists($locale . "-${tagitFieldName}", $tagitKeywords) ? $tagitKeywords[$locale . "-${tagitFieldName}"] : [];
                        }
                        $submitForm->setData($tagitFieldName, $formTagitData);
                    }
                }
                return new JSONMessage(true, $submitForm->fetch($request));
            }
        }
    }

    //
    // Protected helper methods
    //
    /**
     * Setup common template variables.
     *
     * @param Request $request
     */
    public function setupTemplate($request)
    {
        parent::setupTemplate($request);
        // Get steps information.
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('steps', $this->getStepsNumberAndLocaleKeys());
    }

    /**
     * Get the step numbers and their corresponding title locale keys.
     *
     * @return array
     */
    abstract public function getStepsNumberAndLocaleKeys();

    /**
     * Get the number of submission steps.
     *
     * @return int
     */
    abstract public function getStepCount();
}
