<?php

/**
 * @file classes/submission/form/SubmissionSubmitStep1Form.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionSubmitStep1Form
 * @ingroup submission_form
 *
 * @brief Form for Step 1 of author submission.
 */

namespace APP\submission\form;

use APP\core\Application;
use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\core\PKPString;

use PKP\db\DAORegistry;
use PKP\security\Role;
use PKP\submission\form\PKPSubmissionSubmitStep1Form;

class SubmissionSubmitStep1Form extends PKPSubmissionSubmitStep1Form
{
    /**
     * Constructor.
     *
     * @param null|mixed $submission
     */
    public function __construct($context, $submission = null)
    {
        parent::__construct($context, $submission);
        $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'sectionId', 'required', 'author.submit.form.sectionRequired', [DAORegistry::getDAO('SectionDAO'), 'sectionExists'], [$context->getId()]));
    }

    /**
     * @copydoc SubmissionSubmitForm::fetch
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $roleDao = DAORegistry::getDAO('RoleDAO');
        $user = $request->getUser();
        $canSubmitAll = $roleDao->userHasRole($this->context->getId(), $user->getId(), Role::ROLE_ID_MANAGER) ||
            $roleDao->userHasRole($this->context->getId(), $user->getId(), Role::ROLE_ID_SUB_EDITOR) ||
            $roleDao->userHasRole(Application::CONTEXT_SITE, $user->getId(), Role::ROLE_ID_SITE_ADMIN);

        // Get section options for this context
        $sectionDao = DAORegistry::getDAO('SectionDAO'); /** @var SectionDAO $sectionDao */
        $sections = [];
        $sectionsIterator = $sectionDao->getByContextId($this->context->getId(), null, !$canSubmitAll);
        while ($section = $sectionsIterator->next()) {
            if (!$section->getIsInactive()) {
                $sections[$section->getId()] = $section->getLocalizedTitle();
            }
        }
        $sectionOptions = ['0' => ''] + $sections;

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('sectionOptions', $sectionOptions);
        $templateMgr->assign('sectionId', $request->getUserVar('sectionId'));

        // Get section policies for this context
        $sectionPolicies = [];
        foreach ($sectionOptions as $sectionId => $sectionTitle) {
            $section = $sectionDao->getById($sectionId);

            $sectionPolicy = $section ? $section->getLocalizedPolicy() : null;
            if ($this->doesSectionPolicyContainAnyText($sectionPolicy)) {
                $sectionPolicies[$sectionId] = $sectionPolicy;
            }
        }

        $templateMgr->assign('sectionPolicies', $sectionPolicies);

        // Get license options for this context
        $licenseOptions = \Application::getCCLicenseOptions();
        $licenseUrlOptions = ['' => ''];
        foreach ($licenseOptions as $url => $label) {
            $licenseUrlOptions[$url] = __($label);
        }
        $templateMgr->assign('licenseUrlOptions', $licenseUrlOptions);

        return parent::fetch($request, $template, $display);
    }

    /**
     * Checks whether a section policy contains any text (plain / readable).
     */
    private function doesSectionPolicyContainAnyText($sectionPolicy)
    {
        $sectionPolicyPlainText = trim(PKPString::html2text($sectionPolicy));
        return strlen($sectionPolicyPlainText) > 0;
    }

    /**
     * @copydoc PKPSubmissionSubmitStep1Form::initData
     */
    public function initData($data = [])
    {
        if (isset($this->submission)) {
            parent::initData([
                'sectionId' => $this->submission->getCurrentPublication()->getData('sectionId'),
                'licenseUrl' => $this->submission->getCurrentPublication()->getData('licenseUrl'),
            ]);
        } else {
            parent::initData();
        }
    }

    /**
     * Assign form data to user-submitted data.
     */
    public function readInputData()
    {
        $this->readUserVars([
            'sectionId', 'licenseUrl'
        ]);
        parent::readInputData();
    }

    /**
     * Perform additional validation checks
     *
     * @copydoc Form::validate
     */
    public function validate($callHooks = true)
    {
        if (!parent::validate($callHooks)) {
            return false;
        }

        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $sectionDao = DAORegistry::getDAO('SectionDAO'); /** @var SectionDAO $sectionDao */
        $section = $sectionDao->getById($this->getData('sectionId'), $context->getId());
        if (!$section) {
            return false;
        }


        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $sectionDao = DAORegistry::getDAO('SectionDAO'); /** @var SectionDAO $sectionDao */
        $section = $sectionDao->getById($this->getData('sectionId'), $context->getId());

        // Validate that the section ID is attached to this server.
        if (!$section) {
            return false;
        }

        // Ensure that submissions are enabled and the assigned section is activated
        if ($context->getData('disableSubmissions') || $section->getIsInactive()) {
            return false;
        }

        return true;
    }

    /**
     * Save changes to submission.
     *
     * @return int the submission ID
     */
    public function execute(...$functionParams)
    {
        parent::execute(...$functionParams);
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $submission = $this->submission;
        $this->submissionId = $this->submission->getId();

        // OPS: Move the submission to production stage
        $submission->setStageId(WORKFLOW_STAGE_ID_PRODUCTION);
        Repo::submission()->dao->update($submission);

        // OPS: Move comments for moderators discussion to production stage
        $query = $this->getCommentsToEditor($this->submissionId);
        if (isset($query)) {
            $queryDao = DAORegistry::getDAO('QueryDAO'); /** @var QueryDAO $queryDao */
            $query->setStageId(WORKFLOW_STAGE_ID_PRODUCTION);
            $queryDao->updateObject($query);
        }

        return $this->submissionId;
    }

    /**
     * Set the publication data from the form.
     *
     * @param Publication $publication
     * @param Submission $submission
     */
    public function setPublicationData($publication, $submission)
    {
        $publication->setData('sectionId', $this->getData('sectionId'));
        $publication->setData('licenseUrl', $this->getData('licenseUrl'));
        parent::setPublicationData($publication, $submission);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\submission\form\SubmissionSubmitStep1Form', '\SubmissionSubmitStep1Form');
}
