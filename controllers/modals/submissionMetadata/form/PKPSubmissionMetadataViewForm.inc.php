<?php

/**
 * @file controllers/modals/submissionMetadata/form/PKPSubmissionMetadataViewForm.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionMetadataViewForm
 * @ingroup controllers_modals_submissionMetadata_form_SubmissionMetadataViewForm
 *
 * @brief Displays a submission's metadata view.
 */

import('lib.pkp.classes.form.Form');

// Use this class to handle the submission metadata.
import('classes.submission.SubmissionMetadataFormImplementation');

class PKPSubmissionMetadataViewForm extends Form {

	/** The submission used to show metadata information **/
	var $_submission;

	/** The current stage id **/
	var $_stageId;

	/**
	 * Parameters to configure the form template.
	 */
	var $_formParams;

	/** @var SubmissionMetadataFormImplementation */
	var $_metadataFormImplem;

	/**
	 * Constructor.
	 * @param $submissionId integer
	 * @param $stageId integer
	 * @param $formParams array
	 */
	function __construct($submissionId, $stageId = null, $formParams = null, $templateName = 'controllers/modals/submissionMetadata/form/submissionMetadataViewForm.tpl') {
		parent::__construct($templateName);

		$submissionDao = Application::getSubmissionDAO();
		$submissionVersion = isset($formParams['submissionVersion']) ? (int)$formParams['submissionVersion'] : null;
		$submission = $submissionDao->getById((int) $submissionId, null, false, $submissionVersion);

		if ($submission) {
			$this->_submission = $submission;
		}

		$this->_stageId = $stageId;

		$this->_formParams = $formParams;

		if ($submission->getCurrentSubmissionVersion() != $submission->getSubmissionVersion()) {
			if (!isset($this->_formParams)) {
				$this->_formParams = array();
			}

			$this->_formParams["readOnly"] = true;
			$this->_formParams["hideSubmit"] = true;
		}

		$this->_metadataFormImplem = new SubmissionMetadataFormImplementation($this);

		$this->setDefaultFormLocale($submission->getLocale());

		// Validation checks for this form
		$this->_metadataFormImplem->addChecks($submission);
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	//
	// Getters and Setters
	//
	/**
	 * Get the Submission
	 * @return Submission
	 */
	function getSubmission() {
		return $this->_submission;
	}

	/**
	 * Get the Stage Id
	 * @return int
	 */
	function getStageId() {
		return $this->_stageId;
	}

	/**
	 * Get the extra form parameters.
	 */
	function getFormParams() {
		return $this->_formParams;
	}


	//
	// Overridden template methods
	//
	/**
	 * Get the names of fields for which data should be localized
	 * @return array
	 */
	function getLocaleFieldNames() {
		$this->_metadataFormImplem->getLocaleFieldNames();
	}

	/**
	 * Initialize form data with the author name and the submission id.
	 */
	function initData() {
		AppLocale::requireComponents(
			LOCALE_COMPONENT_APP_COMMON,
			LOCALE_COMPONENT_PKP_SUBMISSION,
			LOCALE_COMPONENT_APP_SUBMISSION
		);

		$this->_metadataFormImplem->initData($this->getSubmission());
		parent::initData();
	}

	/**
	 * Fetch the HTML contents of the form.
	 * @see Form::fetch
	 */
	function fetch($request, $template = null, $display = false) {
		$submission = $this->getSubmission();
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'submissionId' =>$submission->getId(),
			'stageId' => $this->getStageId(),
			'formParams' => $this->getFormParams(),
			'submissionVersion' => $submission->getSubmissionVersion(),
		));

		// Tell the form what fields are enabled (and which of those are required)
		$context = $request->getContext();
		$metadataFields = Application::getMetadataFields();
		foreach ($metadataFields as $field) {
			$templateMgr->assign([
				$field . 'Enabled' => !empty($context->getData($field)),
				$field . 'Required' => $context->getData($field) === METADATA_REQUIRE,
			]);
		}
		// Provide available submission languages. (Convert the array
		// of locale symbolic names xx_XX into an associative array
		// of symbolic names => readable names.)
		$supportedSubmissionLocales = $context->getData('supportedSubmissionLocales');
		if (empty($supportedSubmissionLocales)) $supportedSubmissionLocales = array($context->getPrimaryLocale());
		$templateMgr->assign(
			'supportedSubmissionLocaleNames',
			array_flip(array_intersect(
				array_flip(AppLocale::getAllLocales()),
				$supportedSubmissionLocales
			))
		);

		// Get assigned categories
		// We need an array of IDs for the SelectListPanel, but we also need an
		// array of Category objects to use when the metadata form is viewed in
		// readOnly mode. This mode is invoked on the SubmissionMetadataHandler
		// is not available here
		$submissionDao = Application::getSubmissionDAO();
		$categories = $submissionDao->getCategories($submission->getId(), $submission->getContextId());
		$assignedCategories = array();
		$selectedIds = array();
		while ($category = $categories->next()) {
			$assignedCategories[] = $category;
			$selectedIds[] = $category->getId();
		}

		// Get SelectCategoryListPanel data
		import('lib.pkp.classes.components.listPanels.SelectCategoryListPanel');
		$selectCategoryList = new SelectCategoryListPanel(array(
			'title' => 'submission.submit.placement.categories',
			'inputName' => 'categories[]',
			'selected' => $selectedIds,
			'getParams' => array(
				'contextId' => $submission->getContextId(),
			),
		));

		$selectCategoryListData = $selectCategoryList->getConfig();
		$templateMgr->assign(array(
			'hasCategories' => !empty($selectCategoryListData['items']),
			'selectCategoryListData' => $selectCategoryListData,
			'assignedCategories' => $assignedCategories,
		));

		return parent::fetch($request, $template, $display);
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->_metadataFormImplem->readInputData();
	}

	/**
	 * Save changes to submission.
	 */
	function execute() {
		$submission = $this->getSubmission();
		parent::execute();
		// Execute submission metadata related operations.
		$this->_metadataFormImplem->execute($submission, Application::get()->getRequest());

		$submissionDao = Application::getSubmissionDAO();
		$submissionDao->removeCategories($submission->getId());
		if ($this->getData('categories')) {
			foreach ((array) $this->getData('categories') as $categoryId) {
				$submissionDao->addCategory($submission->getId(), (int) $categoryId);
			}
		}
	}

}
