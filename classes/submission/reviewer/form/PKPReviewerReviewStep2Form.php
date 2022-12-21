<?php

/**
 * @file classes/submission/reviewer/form/PKPReviewerReviewStep2Form.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPReviewerReviewStep2Form
 * @ingroup submission_reviewer_form
 *
 * @brief Form for Step 2 of a review.
 */

namespace PKP\submission\reviewer\form;

use APP\submission\Submission;
use APP\template\TemplateManager;
use PKP\core\PKPRequest;
use PKP\submission\reviewAssignment\ReviewAssignment;

class PKPReviewerReviewStep2Form extends ReviewerReviewForm
{
    /**
     * Constructor.
     */
    public function __construct(PKPRequest $request, Submission $reviewSubmission, ReviewAssignment $reviewAssignment)
    {
        parent::__construct($request, $reviewSubmission, $reviewAssignment, 2);
    }


    //
    // Implement protected template methods from Form
    //
    /**
     * @copydoc ReviewerReviewForm::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $context = $this->request->getContext();

        $reviewAssignment = $this->getReviewAssignment();
        $reviewerGuidelines = $context->getLocalizedData($reviewAssignment->getStageId() == WORKFLOW_STAGE_ID_INTERNAL_REVIEW ? 'internalReviewGuidelines' : 'reviewGuidelines');
        if (empty($reviewerGuidelines)) {
            $reviewerGuidelines = __('reviewer.submission.noGuidelines');
        }
        $templateMgr->assign('reviewerGuidelines', $reviewerGuidelines);

        return parent::fetch($request, $template, $display);
    }


    /**
     * @see Form::execute()
     */
    public function execute(...$functionParams)
    {
        // Set review to next step.
        $this->updateReviewStepAndSaveSubmission($this->getReviewAssignment());

        parent::execute(...$functionParams);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\reviewer\form\PKPReviewerReviewStep2Form', '\PKPReviewerReviewStep2Form');
}
