<?php

/**
 * @file pages/submission/SubmissionHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionHandler
 * @ingroup pages_submission
 *
 * @brief Handle requests for the submission wizard.
 */

use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\security\Role;
use Sokil\IsoCodes\IsoCodesFactory;

import('lib.pkp.pages.submission.PKPSubmissionHandler');

class SubmissionHandler extends PKPSubmissionHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_AUTHOR, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_MANAGER],
            ['index', 'wizard', 'step', 'saveStep', 'fetchChoices']
        );
    }


    //
    // Public methods
    //
    /**
     * @copydoc PKPSubmissionHandler::step()
     */
    public function step($args, $request)
    {
        $step = isset($args[0]) ? (int) $args[0] : 1;
        if ($step == $this->getStepCount()) {
            $templateMgr = TemplateManager::getManager($request);
            $context = $request->getContext();
            $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

            // OPS: Check if author can publish
            // OPS: Author can publish, see if other criteria exists and create an array of errors
            if (Repo::publication()->canCurrentUserPublish($submission->getId())) {
                $primaryLocale = $context->getPrimaryLocale();
                $allowedLocales = $context->getSupportedLocales();
                $errors = Repo::publication()->validatePublish($submission->getLatestPublication(), $submission, $allowedLocales, $primaryLocale);

                if (!empty($errors)) {
                    $msg = '<ul class="plain">';
                    foreach ($errors as $error) {
                        $msg .= '<li>' . $error . '</li>';
                    }
                    $msg .= '</ul>';
                    $templateMgr->assign('errors', $msg);
                }
            }
            // OPS: Author can not publish
            else {
                $templateMgr->assign('authorCanNotPublish', true);
            }
        }
        return parent::step($args, $request);
    }

    /**
     * Retrieves a JSON list of available choices for a tagit metadata input field.
     *
     * @param array $args
     * @param Request $request
     */
    public function fetchChoices($args, $request)
    {
        $term = $request->getUserVar('term');
        $locale = $request->getUserVar('locale');
        if (!$locale) {
            $locale = AppLocale::getLocale();
        }
        switch ($request->getUserVar('list')) {
            case 'languages':
                $isoCodes = app(IsoCodesFactory::class);
                $matches = [];
                foreach ($isoCodes->getLanguages(IsoCodesFactory::OPTIMISATION_IO) as $language) {
                    if (!$language->getAlpha2() || $language->getType() != 'L' || $language->getScope() != 'I') {
                        continue;
                    }
                    if (stristr($language->getLocalName(), $term)) {
                        $matches[$language->getAlpha3()] = $language->getLocalName();
                    }
                };
                header('Content-Type: text/json');
                echo json_encode($matches);
        }
        assert(false);
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
        AppLocale::requireComponents(LOCALE_COMPONENT_APP_AUTHOR);
        return parent::setupTemplate($request);
    }

    /**
     * Get the step numbers and their corresponding title locale keys.
     *
     * @return array
     */
    public function getStepsNumberAndLocaleKeys()
    {
        return [
            1 => 'author.submit.start',
            2 => 'author.submit.upload',
            3 => 'author.submit.metadata',
            4 => 'author.submit.confirmation',
            5 => 'author.submit.nextSteps',
        ];
    }

    /**
     * Get the number of submission steps.
     *
     * @return int
     */
    public function getStepCount()
    {
        return 5;
    }
}
