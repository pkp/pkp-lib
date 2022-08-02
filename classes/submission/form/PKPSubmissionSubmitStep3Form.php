<?php

/**
 * @file classes/submission/form/PKPSubmissionSubmitStep3Form.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionSubmitStep3Form
 * @ingroup submission_form
 *
 * @brief Form for Step 3 of author submission: submission metadata
 */

namespace PKP\submission\form;

use APP\core\Application;

use APP\facades\Repo;
use APP\submission\SubmissionMetadataFormImplementation;
use APP\template\TemplateManager;
use PKP\context\Context;
use PKP\submission\SubmissionAgencyDAO;
use PKP\submission\SubmissionDisciplineDAO;
use PKP\submission\SubmissionKeywordDAO;
use PKP\submission\SubmissionLanguageDAO;
use PKP\submission\SubmissionSubjectDAO;

class PKPSubmissionSubmitStep3Form extends SubmissionSubmitForm
{
    public SubmissionMetadataFormImplementation $metadataForm;

    /**
     * Constructor.
     *
     * @param Context $context
     * @param Submission $submission
     * @param MetadataFormImplementation $metadataFormImplementation
     */
    public function __construct($context, $submission, $metadataFormImplementation)
    {
        parent::__construct($context, $submission, 3);

        $this->setDefaultFormLocale($submission->getLocale());
        $this->metadataForm = $metadataFormImplementation;
        $this->metadataForm->addChecks($submission);
    }

    /**
     * @copydoc SubmissionSubmitForm::initData
     */
    public function initData()
    {
        $this->metadataForm->initData($this->submission);
        return parent::initData();
    }

    /**
     * @copydoc SubmissionSubmitForm::fetch
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $context = $request->getContext();

        // Tell the form what fields are enabled (and which of those are required)
        $metadataFields = Application::getMetadataFields();
        $urlTemplate = $request->getDispatcher()->url($request, Application::ROUTE_API, $context->getData('urlPath'), 'vocabs', null, null, ['vocab' => '__vocab__']);
        $controlledVocabMap = [
            'languages' => SubmissionLanguageDAO::CONTROLLED_VOCAB_SUBMISSION_LANGUAGE,
            'subjects' => SubmissionSubjectDAO::CONTROLLED_VOCAB_SUBMISSION_SUBJECT,
            'disciplines' => SubmissionDisciplineDAO::CONTROLLED_VOCAB_SUBMISSION_DISCIPLINE,
            'keywords' => SubmissionKeywordDAO::CONTROLLED_VOCAB_SUBMISSION_KEYWORD,
            'agencies' => SubmissionAgencyDAO::CONTROLLED_VOCAB_SUBMISSION_AGENCY
        ];
        foreach ($metadataFields as $field) {
            $templateMgr->assign([
                $field . 'Enabled' => $context->getData($field) === Context::METADATA_REQUEST || $context->getData($field) === Context::METADATA_REQUIRE,
                $field . 'Required' => $context->getData($field) === Context::METADATA_REQUIRE,
                $field . 'SourceUrl' => isset($controlledVocabMap[$field]) ? str_replace('__vocab__', $controlledVocabMap[$field], $urlTemplate) : null
            ]);
        }

        $templateMgr->assign('publicationId', $this->submission->getCurrentPublication()->getId());

        return parent::fetch($request, $template, $display);
    }

    /**
     * Assign form data to user-submitted data.
     */
    public function readInputData()
    {
        $this->metadataForm->readInputData();
    }

    /**
     * Get the names of fields for which data should be localized
     *
     * @return array
     */
    public function getLocaleFieldNames()
    {
        return $this->metadataForm->getLocaleFieldNames();
    }

    /**
     * Save changes to submission.
     *
     * @return int the submission ID
     */
    public function execute(...$functionArgs)
    {
        // Execute submission metadata related operations.
        $this->metadataForm->execute($this->submission, Application::get()->getRequest());

        // Get an updated version of the submission.
        $this->submission = Repo::submission()->get($this->submissionId);

        // Set other submission data.
        if ($this->submission->getSubmissionProgress() <= $this->step) {
            $this->submission->setSubmissionProgress($this->step + 1);
            $this->submission->stampLastActivity();
            $this->submission->stampModified();
        }

        parent::execute(...$functionArgs);

        // Save the submission.
        Repo::submission()->dao->update($this->submission);

        return $this->submissionId;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\form\PKPSubmissionSubmitStep3Form', '\PKPSubmissionSubmitStep3Form');
}
