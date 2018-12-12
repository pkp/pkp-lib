<?php

/**
 * @file classes/submission/SubmissionMetadataFormImplementation.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
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
		$this->_parentForm->addCheck(new FormValidatorLocale($this->_parentForm, 'title', 'required', 'submission.submit.form.titleRequired', $submission->getLocale()));
		if ($this->_getAbstractsRequired($submission)) {
			$this->_parentForm->addCheck(new FormValidatorLocale($this->_parentForm, 'abstract', 'required', 'submission.submit.form.abstractRequired', $submission->getLocale()));
		}

		// Validates that at least one author has been added.
		$this->_parentForm->addCheck(new FormValidatorCustom(
			$this->_parentForm, 'authors', 'required', 'submission.submit.form.authorRequired',
			function() use ($submission) {
				return count($submission->getAuthors()) > 0;
			}
		));

		$contextDao = Application::getContextDao();
		$context = $contextDao->getById($submission->getContextId());
		$metadataFields = Application::getMetadataFields();
		foreach ($metadataFields as $field) {
			$requiredLocaleKey = 'submission.submit.form.'.$field.'Required';
			if ($context->getData($field) === METADATA_REQUIRE) {
				switch(1) {
					case in_array($field, $this->getLocaleFieldNames()):
						$this->_parentForm->addCheck(new FormValidatorLocale($this->_parentForm, $field, 'required', $requiredLocaleKey, $submission->getLocale()));
						break;
					case in_array($field, $this->getTagitFieldNames()):
						$this->_parentForm->addCheck(new FormValidatorCustom($this->_parentForm, $field, 'required', $requiredLocaleKey, create_function('$field,$form,$name', '$data = (array) $form->getData(\'keywords\'); return array_key_exists($name, $data);'), array($this->_parentForm, $submission->getLocale().'-'.$field)));
						break;
					case $key == 'citations':
						$form = $this->_parentForm;
						$this->_parentForm->addCheck(new FormValidatorCustom($this->_parentForm, $key, 'required', $requiredLocaleKey, function($key) use ($form) {
							$metadataModal = $form->getData('metadataModal');
							if (!$metadataModal) {
								$references = $form->getData('citations');
								return !empty($references);
							}
							return true;
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
			$formData = array(
				'title' => $submission->getTitle(null, false), // Localized
				'prefix' => $submission->getPrefix(null), // Localized
				'subtitle' => $submission->getSubtitle(null), // Localized
				'abstract' => $submission->getAbstract(null), // Localized
				'coverage' => $submission->getCoverage(null), // Localized
				'type' => $submission->getType(null), // Localized
				'source' =>$submission->getSource(null), // Localized
				'rights' => $submission->getRights(null), // Localized
				'citations' => $submission->getCitations(),
				'locale' => $submission->getLocale(),
			);

			foreach ($formData as $key => $data) {
				$this->_parentForm->setData($key, $data);
			}

			// get the supported locale keys
			$locales = array_keys($this->_parentForm->supportedLocales);

			// load the persisted metadata controlled vocabularies
			$submissionKeywordDao = DAORegistry::getDAO('SubmissionKeywordDAO');
			$submissionSubjectDao = DAORegistry::getDAO('SubmissionSubjectDAO');
			$submissionDisciplineDao = DAORegistry::getDAO('SubmissionDisciplineDAO');
			$submissionAgencyDao = DAORegistry::getDAO('SubmissionAgencyDAO');
			$submissionLanguageDao = DAORegistry::getDAO('SubmissionLanguageDAO');

			$this->_parentForm->setData('subjects', $submissionSubjectDao->getSubjects($submission->getId(), $locales));
			$this->_parentForm->setData('keywords', $submissionKeywordDao->getKeywords($submission->getId(), $locales));
			$this->_parentForm->setData('disciplines', $submissionDisciplineDao->getDisciplines($submission->getId(), $locales));
			$this->_parentForm->setData('agencies', $submissionAgencyDao->getAgencies($submission->getId(), $locales));
			$this->_parentForm->setData('languages', $submissionLanguageDao->getLanguages($submission->getId(), $locales));
			$this->_parentForm->setData('abstractsRequired', $this->_getAbstractsRequired($submission));
		}
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		// 'keywords' is a tagit catchall that contains an array of values for each keyword/locale combination on the form.
		$userVars = array('title', 'prefix', 'subtitle', 'abstract', 'coverage', 'type', 'source', 'rights', 'keywords', 'citations', 'locale', 'metadataModal', 'categories');
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
		$submissionDao = Application::getSubmissionDAO();

		// Update submission
		$submission->setTitle($this->_parentForm->getData('title'), null); // Localized
		$submission->setPrefix($this->_parentForm->getData('prefix'), null); // Localized
		$submission->setSubtitle($this->_parentForm->getData('subtitle'), null); // Localized
		$submission->setAbstract($this->_parentForm->getData('abstract'), null); // Localized
		$submission->setCoverage($this->_parentForm->getData('coverage'), null); // Localized
		$submission->setType($this->_parentForm->getData('type'), null); // Localized
		$submission->setRights($this->_parentForm->getData('rights'), null); // Localized
		$submission->setSource($this->_parentForm->getData('source'), null); // Localized
		$metadataModal = $this->_parentForm->getData('metadataModal');
		if (!$metadataModal) {
			$submission->setCitations($this->_parentForm->getData('citations'));
		}

		// Update submission locale
		$newLocale = $this->_parentForm->getData('locale');
		$context = $request->getContext();
		$supportedSubmissionLocales = $context->getData('supportedSubmissionLocales');
		if (empty($supportedSubmissionLocales)) $supportedSubmissionLocales = array($context->getPrimaryLocale());
		if (in_array($newLocale, $supportedSubmissionLocales)) $submission->setLocale($newLocale);

		// Save the submission
		$submissionDao->updateObject($submission);

		// get the supported locale keys
		$locales = array_keys($this->_parentForm->supportedLocales);

		// persist the metadata/keyword fields.
		$submissionKeywordDao = DAORegistry::getDAO('SubmissionKeywordDAO');
		$submissionSubjectDao = DAORegistry::getDAO('SubmissionSubjectDAO');
		$submissionDisciplineDao = DAORegistry::getDAO('SubmissionDisciplineDAO');
		$submissionAgencyDao = DAORegistry::getDAO('SubmissionAgencyDAO');
		$submissionLanguageDao = DAORegistry::getDAO('SubmissionLanguageDAO');

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
		$submissionKeywordDao->insertKeywords($keywords, $submission->getId());
		$submissionAgencyDao->insertAgencies($agencies, $submission->getId());
		$submissionDisciplineDao->insertDisciplines($disciplines, $submission->getId());
		$submissionLanguageDao->insertLanguages($languages, $submission->getId());
		$submissionSubjectDao->insertSubjects($subjects, $submission->getId());

		// Resequence the authors (this ensures a primary contact).
		$authorDao = DAORegistry::getDAO('AuthorDAO');
		$authorDao->resequenceAuthors($submission->getId());

		// Save the submission categories
		$submissionDao = Application::getSubmissionDAO();
		$submissionDao->removeCategories($submission->getId());
		if ($categories = $this->_parentForm->getData('categories')) {
			foreach ((array) $categories as $categoryId) {
				$submissionDao->addCategory($submission->getId(), (int) $categoryId);
			}
		}

		// Only log modifications on completed submissions
		if ($submission->getSubmissionProgress() == 0) {
			// Log the metadata modification event.
			import('lib.pkp.classes.log.SubmissionLog');
			import('classes.log.SubmissionEventLogEntry');
			SubmissionLog::logEvent($request, $submission, SUBMISSION_LOG_METADATA_UPDATE, 'submission.event.general.metadataUpdated');
		}
	}
}
