<?php

/**
 * @file classes/submission/form/SubmissionSubmitStep2Form.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionSubmitStep2Form
 * @ingroup submission_form
 *
 * @brief Form for Step 2 of author manuscript submission.
 */

use \APP\template\TemplateManager;

import('lib.pkp.classes.submission.form.PKPSubmissionSubmitStep2Form');

class SubmissionSubmitStep2Form extends PKPSubmissionSubmitStep2Form
{
    /**
     * Constructor.
     */
    public function __construct($context, $submission)
    {
        parent::__construct($context, $submission);
    }

    /**
     * @copydoc SubmissionSubmitForm::fetch
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $requestArgs['submissionId'] = $this->submission->getId();
        $requestArgs['publicationId'] = $this->submission->getCurrentPublication()->getId();
        $templateMgr->assign('requestArgs', $requestArgs);
        return parent::fetch($request, $template, $display);
    }
}
