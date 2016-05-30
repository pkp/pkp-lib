<?php

/**
 * @file classes/submission/SubmissionMetadataFormImplementation.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
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
	function PKPSubmissionMetadataFormImplementation($parentForm = null) {
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
		$this->_parentForm->addCheck(new FormValidatorLocale($this->_parentForm, 'title', 'required', 'submission.submit.form.titleRequired'));
		if ($this->_getAbstractsRequired($submission)) {
			$this->_parentForm->addCheck(new FormValidatorLocale($this->_parentForm, 'abstract', 'required', 'submission.submit.form.abstractRequired'));
		}

		// Validates that at least one author has been added (note that authors are in grid, so Form does not
		// directly see the authors value (there is no "authors" input. Hence the $ignore parameter.
		$this->_parentForm->addCheck(new FormValidatorCustom(
			$this->_parentForm, 'authors', 'required', 'submission.submit.form.authorRequired',
			// The first parameter is ignored. This
			create_function('$ignore, $submission', 'return count($submission->getAuthors()) > 0;'),
			array($submission)
		));
	}

	/**
	 * Initialize form data from current submission.
	 * @param $submission Submission
	 * @param $revision int
	 */
	function initData($submission, $revision = null) {
		if (isset($submission)) {
			$formData = array(
				'title' => $submission->getTitle(null), // Localized
				'prefix' => $submission->getPrefix(null), // Localized
				'subtitle' => $submission->getSubtitle(null), // Localized
				'abstract' => $submission->getAbstract(null), // Localized
				'coverage' => $submission->getCoverage(null), // Localized
				'type' => $submission->getType(null), // Localized
				'source' =>$submission->getSource(null), // Localized
				'rights' => $submission->getRights(null), // Localized
				'citations' => $submission->getCitations(),
				'hideSubmissionRevisions' => $submission->getHideSubmissionRevisions(),
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

			$contextId = Request::getContext()->getId();
			$latestRevisionId = $submission->getCurrentVersionId($contextId);
			if (!$revision) $revision = $latestRevisionId;
			
			$this->_parentForm->setData('subjects', $submissionSubjectDao->getSubjects($submission->getId(), $locales, $revision));
			$this->_parentForm->setData('keywords', $submissionKeywordDao->getKeywords($submission->getId(), $locales, $revision));
			$this->_parentForm->setData('disciplines', $submissionDisciplineDao->getDisciplines($submission->getId(), $locales, $revision));
			$this->_parentForm->setData('agencies', $submissionAgencyDao->getAgencies($submission->getId(), $locales, $revision));
			$this->_parentForm->setData('languages', $submissionLanguageDao->getLanguages($submission->getId(), $locales, $revision));
			$this->_parentForm->setData('abstractsRequired', $this->_getAbstractsRequired($submission));
			$this->_parentForm->setData('latestRevisionId', $latestRevisionId);
		}
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		// 'keywords' is a tagit catchall that contains an array of values for each keyword/locale combination on the form.
		$userVars = array('title', 'prefix', 'subtitle', 'abstract', 'coverage', 'type', 'source', 'rights', 'keywords', 'citations');
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
		$submission->setCitations($this->_parentForm->getData('citations'));
		$submission->setHideSubmissionRevisions($request->getUserVar('hideSubmissionRevisions') ? 1 : 0);

		if ($request->getUserVar('submissionRevision')) {
			$revision = (int)$request->getUserVar('submissionRevision');
		} else {
			$router = $request->getRouter();
			$contextId = $router->getContext()->getId();
			$revision = $submission->getCurrentVersionId($contextId);
		}

		if ($request->getUserVar('saveAsRevision')) {
			$authorDao = DAORegistry::getDAO('AuthorDAO');
			$authors = $authorDao->getBySubmissionId($submission->getId(), true, false, $revision);
			
			$revision++;
			// copy the authors from the old version into the new version
			// (the author_id is retained unchanged as the primary key for the authors table is [author_id, version])
			foreach($authors as $author) {
				$authorId = (int)$author->getId();
				$author->setVersion($revision);
				$authorDao->insertObject($author, true);
				$newAuthorId = (int)$authorDao->getInsertId();
				$authorDao->update('UPDATE authors SET author_id = ? WHERE author_id = ?', array($authorId, $newAuthorId));
			}
		}
		$submission->setData('submissionRevision', $revision);

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
				$keywords[$locale] = array_key_exists($locale . '-keyword', $tagitKeywords) ? $tagitKeywords[$locale . '-keyword'] : array();
				$agencies[$locale] = array_key_exists($locale . '-agencies', $tagitKeywords) ? $tagitKeywords[$locale . '-agencies'] : array();
				$disciplines[$locale] = array_key_exists($locale . '-disciplines', $tagitKeywords) ? $tagitKeywords[$locale . '-disciplines'] : array();
				$languages[$locale] = array_key_exists($locale . '-languages', $tagitKeywords) ? $tagitKeywords[$locale . '-languages'] : array();
				$subjects[$locale] = array_key_exists($locale . '-subjects', $tagitKeywords) ?$tagitKeywords[$locale . '-subjects'] : array();
			}
		}

		// persist the controlled vocabs
		$submissionKeywordDao->insertKeywords($keywords, $submission->getId(), true, $revision);
		$submissionAgencyDao->insertAgencies($agencies, $submission->getId(), true, $revision);
		$submissionDisciplineDao->insertDisciplines($disciplines, $submission->getId(), true, $revision);
		$submissionLanguageDao->insertLanguages($languages, $submission->getId(), true, $revision);
		$submissionSubjectDao->insertSubjects($subjects, $submission->getId(), true, $revision);

		// Resequence the authors (this ensures a primary contact).
		$authorDao = DAORegistry::getDAO('AuthorDAO');
		$authorDao->resequenceAuthors($submission->getId());

		// Only log modifications on completed submissions
		if ($submission->getSubmissionProgress() == 0) {
			// Log the metadata modification event.
			import('lib.pkp.classes.log.SubmissionLog');
			import('classes.log.SubmissionEventLogEntry');
			SubmissionLog::logEvent($request, $submission, SUBMISSION_LOG_METADATA_UPDATE, 'submission.event.general.metadataUpdated');
		}
	}
}

?>
