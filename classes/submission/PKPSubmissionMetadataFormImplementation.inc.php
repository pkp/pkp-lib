<?php

/**
 * @file classes/submission/PKPSubmissionMetadataFormImplementation.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionMetadataFormImplementation
 * @ingroup submission
 *
 * @brief This can be used by other forms that want to
 * implement submission metadata data and form operations.
 */

class PKPSubmissionMetadataFormImplementation {

	/** @var Form Form that uses this implementation */
	var $_parentForm;

	/**
	 * Constructor.
	 * @param $parentForm Form A form that can use this form.
	 */
	function __construct($parentForm = null) {
		assert(is_a($parentForm, 'Form'));
		$this->_parentForm = $parentForm;
	}

	/**
	 * Determine whether or not abstracts are required.
	 * @param $submission Submission
	 * @return boolean
	 */
	function _getAbstractsRequired($submission) {
		return true; // Required by default
	}

	/**
	 * Add checks to form.
	 * @param $submission Submission
	 */
	function addChecks($submission) {
		import('lib.pkp.classes.form.validation.FormValidatorLocale');
		import('lib.pkp.classes.form.validation.FormValidatorCustom');

		// Validation checks.
		$this->_parentForm->addCheck(new FormValidatorLocale($this->_parentForm, 'title', 'required', 'submission.submit.form.titleRequired', $submission->getCurrentPublication()->getData('locale')));
		if ($this->_getAbstractsRequired($submission)) {
			$this->_parentForm->addCheck(new FormValidatorLocale($this->_parentForm, 'abstract', 'required', 'submission.submit.form.abstractRequired', $submission->getCurrentPublication()->getData('locale')));
		}

		// Validates that at least one author has been added.
		$this->_parentForm->addCheck(new FormValidatorCustom(
			$this->_parentForm, 'authors', 'required', 'submission.submit.form.authorRequired',
			function() use ($submission) {
				return !empty($submission->getCurrentPublication()->getData('authors'));
			}
		));

		$contextDao = Application::getContextDao();
		$context = $contextDao->getById($submission->getContextId());
		$metadataFields = Application::getMetadataFields();
		foreach ($metadataFields as $field) {
			$requiredLocaleKey = 'submission.submit.form.'.$field.'Required';
			if ($context->getData($field) === METADATA_REQUIRE) {
				switch($field) {
					case in_array($field, $this->getLocaleFieldNames()):
						$this->_parentForm->addCheck(new FormValidatorLocale($this->_parentForm, $field, 'required', $requiredLocaleKey, $submission->getCurrentPublication()->getData('locale')));
						break;
					case in_array($field, $this->getTagitFieldNames()):
						$this->_parentForm->addCheck(new FormValidatorCustom($this->_parentForm, $field, 'required', $requiredLocaleKey, create_function('$field,$form,$name', '$data = (array) $form->getData(\'keywords\'); return array_key_exists($name, $data);'), array($this->_parentForm, $submission->getCurrentPublication()->getData('locale').'-'.$field)));
						break;
					case 'citations':
						$form = $this->_parentForm;
						$this->_parentForm->addCheck(new FormValidatorCustom($this->_parentForm, 'citationsRaw', 'required', $requiredLocaleKey, function($key) use ($form) {
							return !empty($form->getData('citationsRaw'));
						}));
						break;
					default:
						$this->_parentForm->addCheck(new FormValidator($this->_parentForm, $field, 'required', $requiredLocaleKey));
				}
			}
		}
	}

	/**
	 * Initialize form data from current submission.
	 * @param $submission Submission
	 */
	function initData($submission) {
		if (isset($submission)) {
			$publication = $submission->getCurrentPublication();
			$formData = array(
				'title' => $publication->getData('title'),
				'prefix' => $publication->getData('prefix'),
				'subtitle' => $publication->getData('subtitle'),
				'abstract' => $publication->getData('abstract'),
				'coverage' => $publication->getData('coverage'),
				'type' => $publication->getData('type'),
				'source' =>$publication->getData('source'),
				'rights' => $publication->getData('rights'),
				'citationsRaw' => $publication->getData('citationsRaw'),
				'locale' => $publication->getData('locale'),
			);

			foreach ($formData as $key => $data) {
				$this->_parentForm->setData($key, $data);
			}

			// get the supported locale keys
			$locales = array_keys($this->_parentForm->supportedLocales);

			// load the persisted metadata controlled vocabularies
			$submissionKeywordDao = DAORegistry::getDAO('SubmissionKeywordDAO'); /* @var $submissionKeywordDao SubmissionKeywordDAO */
			$submissionSubjectDao = DAORegistry::getDAO('SubmissionSubjectDAO'); /* @var $submissionSubjectDao SubmissionSubjectDAO */
			$submissionDisciplineDao = DAORegistry::getDAO('SubmissionDisciplineDAO'); /* @var $submissionDisciplineDao SubmissionDisciplineDAO */
			$submissionAgencyDao = DAORegistry::getDAO('SubmissionAgencyDAO'); /* @var $submissionAgencyDao SubmissionAgencyDAO */
			$submissionLanguageDao = DAORegistry::getDAO('SubmissionLanguageDAO'); /* @var $submissionLanguageDao SubmissionLanguageDAO */

			$this->_parentForm->setData('subjects', $submissionSubjectDao->getSubjects($publication->getId(), $locales));
			$this->_parentForm->setData('keywords', $submissionKeywordDao->getKeywords($publication->getId(), $locales));
			$this->_parentForm->setData('disciplines', $submissionDisciplineDao->getDisciplines($publication->getId(), $locales));
			$this->_parentForm->setData('agencies', $submissionAgencyDao->getAgencies($publication->getId(), $locales));
			$this->_parentForm->setData('languages', $submissionLanguageDao->getLanguages($publication->getId(), $locales));
			$this->_parentForm->setData('abstractsRequired', $this->_getAbstractsRequired($submission));
		}
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		// 'keywords' is a tagit catchall that contains an array of values for each keyword/locale combination on the form.
		$userVars = array('title', 'prefix', 'subtitle', 'abstract', 'coverage', 'type', 'source', 'rights', 'keywords', 'citationsRaw', 'locale');
		$this->_parentForm->readUserVars($userVars);
	}

	/**
	 * Get the names of fields for which data should be localized
	 * @return array
	 */
	function getLocaleFieldNames() {
		return array('title', 'prefix', 'subtitle', 'abstract', 'coverage', 'type', 'source', 'rights');
	}

	/**
	 * Get the names of fields for which tagit is used
	 * @return array
	 */
	function getTagitFieldNames() {
		return array('subjects', 'keywords', 'disciplines', 'agencies', 'languages');
	}

	/**
	 * Save changes to submission.
	 * @param $submission Submission
	 * @param $request PKPRequest
	 * @return Submission
	 */
	function execute($submission, $request) {
		$publication = $submission->getCurrentPublication();
		$authorDao = DAORegistry::getDAO('AuthorDAO'); /* @var $authorDao AuthorDAO */
		$context = $request->getContext();

		// Get params to update
		$params = [
			'title' => $this->_parentForm->getData('title'),
			'prefix' => $this->_parentForm->getData('prefix'),
			'subtitle' => $this->_parentForm->getData('subtitle'),
			'abstract' => $this->_parentForm->getData('abstract'),
			'coverage' => $this->_parentForm->getData('coverage'),
			'type' => $this->_parentForm->getData('type'),
			'rights' => $this->_parentForm->getData('rights'),
			'source' => $this->_parentForm->getData('source'),
			'citationsRaw' => $this->_parentForm->getData('citationsRaw'),
		];

		// Update locale
		$newLocale = $this->_parentForm->getData('locale');
		if ($newLocale) {
			$oldLocale = $publication->getData('locale');
			if (in_array($newLocale, $context->getData('supportedSubmissionLocales'))) {
				$params['locale'] = $newLocale;
			}
			if ($newLocale !== $oldLocale) {
				$authorDao->changePublicationLocale($publication->getId(), $oldLocale, $newLocale);
			}
		}

		// Save the publication
		$publication = Services::get('publication')->edit($publication, $params, $request);

		// get the supported locale keys
		$locales = array_keys($this->_parentForm->supportedLocales);

		// persist the metadata/keyword fields.
		$submissionKeywordDao = DAORegistry::getDAO('SubmissionKeywordDAO'); /* @var $submissionKeywordDao SubmissionKeywordDAO */
		$submissionSubjectDao = DAORegistry::getDAO('SubmissionSubjectDAO'); /* @var $submissionSubjectDao SubmissionSubjectDAO */
		$submissionDisciplineDao = DAORegistry::getDAO('SubmissionDisciplineDAO'); /* @var $submissionDisciplineDao SubmissionDisciplineDAO */
		$submissionAgencyDao = DAORegistry::getDAO('SubmissionAgencyDAO'); /* @var $submissionAgencyDao SubmissionAgencyDAO */
		$submissionLanguageDao = DAORegistry::getDAO('SubmissionLanguageDAO'); /* @var $submissionLanguageDao SubmissionLanguageDAO */

		$keywords = array();
		$agencies = array();
		$disciplines = array();
		$languages = array();
		$subjects = array();

		$tagitKeywords = $this->_parentForm->getData('keywords');

		if (is_array($tagitKeywords)) {
			foreach ($locales as $locale) {
				$keywords[$locale] = array_key_exists($locale . '-keywords', $tagitKeywords) ? $tagitKeywords[$locale . '-keywords'] : array();
				$agencies[$locale] = array_key_exists($locale . '-agencies', $tagitKeywords) ? $tagitKeywords[$locale . '-agencies'] : array();
				$disciplines[$locale] = array_key_exists($locale . '-disciplines', $tagitKeywords) ? $tagitKeywords[$locale . '-disciplines'] : array();
				$languages[$locale] = array_key_exists($locale . '-languages', $tagitKeywords) ? $tagitKeywords[$locale . '-languages'] : array();
				$subjects[$locale] = array_key_exists($locale . '-subjects', $tagitKeywords) ?$tagitKeywords[$locale . '-subjects'] : array();
			}
		}

		// persist the controlled vocabs
		$submissionKeywordDao->insertKeywords($keywords, $submission->getCurrentPublication()->getId());
		$submissionAgencyDao->insertAgencies($agencies, $submission->getCurrentPublication()->getId());
		$submissionDisciplineDao->insertDisciplines($disciplines, $submission->getCurrentPublication()->getId());
		$submissionLanguageDao->insertLanguages($languages, $submission->getCurrentPublication()->getId());
		$submissionSubjectDao->insertSubjects($subjects, $submission->getCurrentPublication()->getId());

		// Only log modifications on completed submissions
		if ($submission->getSubmissionProgress() == 0) {
			// Log the metadata modification event.
			import('lib.pkp.classes.log.SubmissionLog');
			import('classes.log.SubmissionEventLogEntry');
			SubmissionLog::logEvent($request, $submission, SUBMISSION_LOG_METADATA_UPDATE, 'submission.event.general.metadataUpdated');
		}
	}
}
