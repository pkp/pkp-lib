<?php

/**
 * @file classes/submission/form/PKPSubmissionSubmitStep3Form.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionSubmitStep3Form
 * @ingroup submission_form
 *
 * @brief Form for Step 3 of author submission: submission metadata
 */

import('lib.pkp.classes.submission.form.SubmissionSubmitForm');

class PKPSubmissionSubmitStep3Form extends SubmissionSubmitForm {

	/** @var SubmissionMetadataFormImplementation */
	var $_metadataFormImplem;

	/**
	 * Constructor.
	 * @param $context Context
	 * @param $submission Submission
	 * @param $metadataFormImplementation MetadataFormImplementation
	 */
	function __construct($context, $submission, $metadataFormImplementation) {
		parent::__construct($context, $submission, 3);

		$this->setDefaultFormLocale($submission->getLocale());
		$this->_metadataFormImplem = $metadataFormImplementation;
		$this->_metadataFormImplem->addChecks($submission);
	}

	/**
	 * @copydoc SubmissionSubmitForm::initData
	 */
	function initData() {
		$this->_metadataFormImplem->initData($this->submission);
		return parent::initData();
	}

	/**
	 * @copydoc SubmissionSubmitForm::fetch
	 */
	function fetch($request, $template = null, $display = false) {
		$templateMgr = TemplateManager::getManager($request);
		$context = $request->getContext();

		// Tell the form what fields are enabled (and which of those are required)
		$metadataFields = Application::getMetadataFields();
		foreach ($metadataFields as $field) {
			$templateMgr->assign(array(
				$field . 'Enabled' => $context->getData($field) === METADATA_REQUEST || $context->getData($field) === METADATA_REQUIRE,
				$field . 'Required' => $context->getData($field) === METADATA_REQUIRE,
			));
		}

		// Get assigned categories
		// We need an array of IDs for the SelectListPanel, but we also need an
		// array of Category objects to use when the metadata form is viewed in
		// readOnly mode. This mode is invoked on the SubmissionMetadataHandler
		// is not available here
		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->getById($this->submissionId);
		$categories = $submissionDao->getCategories($submission->getId(), $submission->getContextId());
		$assignedCategories = array();
		$selectedIds = array();
		while ($category = $categories->next()) {
			$assignedCategories[] = $category;
			$selectedIds[] = $category->getId();
		}

		// Categories list
		$items = [];
		$categoryDao = DAORegistry::getDAO('CategoryDAO');
		$categories = $categoryDao->getByContextId($context->getId());
		if (!$categories->wasEmpty) {
			while ($category = $categories->next()) {
				$items[] = array(
					'id' => $category->getId(),
					'title' => $category->getLocalizedTitle(),
				);
			}
		}

		$categoriesList = new \PKP\components\listPanels\ListPanel(
			'categories',
			__('grid.category.categories'),
			[
				'canSelect' => true,
				'items' => $items,
				'itemsMax' => count($items),
				'selected' => $selectedIds,
				'selectorName' => 'categories[]',
			]
		);

		$templateMgr->assign(array(
			'assignedCategories' => $assignedCategories,
			'hasCategories' => !empty($categoriesList->items),
			'categoriesListData' => [
				'components' => [
					'categories' => $categoriesList->getConfig(),
				]
			]
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
	 * Get the names of fields for which data should be localized
	 * @return array
	 */
	function getLocaleFieldNames() {
		return $this->_metadataFormImplem->getLocaleFieldNames();
	}

	/**
	 * Save changes to submission.
	 * @return int the submission ID
	 */
	function execute() {
		// Execute submission metadata related operations.
		$this->_metadataFormImplem->execute($this->submission, Application::get()->getRequest());

		// Get an updated version of the submission.
		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->getById($this->submissionId);

		// Set other submission data.
		if ($submission->getSubmissionProgress() <= $this->step) {
			$submission->setSubmissionProgress($this->step + 1);
			$submission->stampStatusModified();
		}

		parent::execute();

		// Save the submission.
		$submissionDao->updateObject($submission);

		return $this->submissionId;
	}
}
