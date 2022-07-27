<?php
/**
 * @defgroup submission_form Submission Forms
 */

/**
 * @file classes/submission/form/SubmissionSubmitForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionSubmitForm
 * @ingroup submission_form
 *
 * @brief Base class for author submit forms.
 */

namespace PKP\submission\form;

use APP\submission\Submission;
use APP\template\TemplateManager;

use PKP\form\Form;

class SubmissionSubmitForm extends Form
{
    /** @var Context */
    public $context;

    /** @var int the ID of the submission */
    public $submissionId;

    /** @var Submission current submission */
    public $submission;

    /** @var int the current step */
    public $step;

    /**
     * Constructor.
     *
     * @param object $submission
     * @param int $step
     */
    public function __construct($context, $submission, $step)
    {
        parent::__construct(
            sprintf('submission/form/step%d.tpl', $step),
            true,
            $submission ? $submission->getLocale() : null,
            $context->getSupportedSubmissionLocaleNames()
        );
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
        $this->step = (int) $step;
        $this->submission = $submission;
        $this->submissionId = $submission ? $submission->getId() : null;
        $this->context = $context;
    }

    /**
     * @copydoc Form::fetch
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);

        $templateMgr->assign('submissionId', $this->submissionId);
        $templateMgr->assign('submitStep', $this->step);

        if (isset($this->submission)) {
            $submissionProgress = $this->submission->getSubmissionProgress();
        } else {
            $submissionProgress = 1;
        }
        $templateMgr->assign('submissionProgress', $submissionProgress);
        return parent::fetch($request, $template, $display);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\form\SubmissionSubmitForm', '\SubmissionSubmitForm');
}
