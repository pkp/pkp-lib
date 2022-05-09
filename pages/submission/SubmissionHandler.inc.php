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
use PKP\core\PKPApplication;
use PKP\security\Role;

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
            [Role::ROLE_ID_AUTHOR, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
            ['index', 'wizard', 'step', 'saveStep']
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
            $submission = $this->getAuthorizedContextObject(PKPApplication::ASSOC_TYPE_SUBMISSION);

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
