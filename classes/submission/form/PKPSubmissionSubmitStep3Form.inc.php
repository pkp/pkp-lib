<?php

/**
 * @file classes/submission/form/PKPSubmissionSubmitStep3Form.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
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
	function PKPSubmissionSubmitStep3Form($context, $submission, $metadataFormImplementation) {
		parent::SubmissionSubmitForm($context, $submission, 3);

		$this->_metadataFormImplem = $metadataFormImplementation;
		$this->_metadataFormImplem->addChecks($submission);
	}

	/**
	 * Initialize form data from current submission.
	 */
	function initData() {
		$this->_metadataFormImplem->initData($this->submission);
		return parent::initData();
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
	 * @param $args array
	 * @param $request PKPRequest
	 * @return int the submission ID
	 */
	function execute($args, $request) {
		// Execute submission metadata related operations.
		$this->_metadataFormImplem->execute($this->submission, $request);

		// Get an updated version of the submission.
		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->getById($this->submissionId);

		// Set other submission data.
		if ($submission->getSubmissionProgress() <= $this->step) {
			$submission->setSubmissionProgress($this->step + 1);
			$submission->stampStatusModified();
		}

		parent::execute($submission);

		// Save the submission.
		$submissionDao->updateObject($submission);

		return $this->submissionId;
	}
}

?>
