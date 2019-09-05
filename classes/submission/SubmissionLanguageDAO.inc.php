<?php

/**
 * @file classes/submission/SubmissionLanguageDAO.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionLanguageDAO
 * @ingroup submission
 * @see Submission
 *
 * @brief Operations for retrieving and modifying a submission's assigned languages
 */

import('lib.pkp.classes.controlledVocab.ControlledVocabDAO');

define('CONTROLLED_VOCAB_SUBMISSION_LANGUAGE', 'submissionLanguage');

class SubmissionLanguageDAO extends ControlledVocabDAO {

	/**
	 * Build/fetch and return a controlled vocabulary for languages.
	 * @param $publicationId int
	 * @return ControlledVocab
	 */
	function build($publicationId) {
		// may return an array of ControlledVocabs
		return parent::_build(CONTROLLED_VOCAB_SUBMISSION_LANGUAGE, ASSOC_TYPE_PUBLICATION, $publicationId);
	}

	/**
	 * Get the list of localized additional fields to store.
	 * @return array
	 */
	function getLocaleFieldNames() {
		return array('submissionLanguage');
	}

	/**
	 * Get Languages for a submission.
	 * @param $publicationId int
	 * @param $locales array
	 * @return array
	 */
	function getLanguages($publicationId, $locales = []) {
		$result = [];

		$languages = $this->build($publicationId);
		$submissionLanguageEntryDao = DAORegistry::getDAO('SubmissionLanguageEntryDAO');
		$submissionLanguages = $submissionLanguageEntryDao->getByControlledVocabId($languages->getId());
		while ($languageEntry = $submissionLanguages->next()) {
			$language = $languageEntry->getLanguage();
			foreach ($language as $locale => $value) {
				if (empty($locales) || in_array($locale, $locales)) {
					if (!array_key_exists($locale, $result)) {
						$result[$locale] = [];
					}
					$result[$locale][] = $value;
				}
			}
		}

		return $result;
	}

	/**
	 * Get an array of all of the submission's Languages
	 * @return array
	 */
	function getAllUniqueLanguages() {
		$languages = array();

		$result = $this->retrieve(
			'SELECT DISTINCT setting_value FROM controlled_vocab_entry_settings WHERE setting_name = ?', CONTROLLED_VOCAB_SUBMISSION_LANGUAGE
		);

		while (!$result->EOF) {
			$languages[] = $result->fields[0];
			$result->MoveNext();
		}

		$result->Close();
		return $languages;
	}

	/**
	 * Add an array of languages
	 * @param $languages array
	 * @param $publicationId int
	 * @param $deleteFirst boolean
	 * @return int
	 */
	function insertLanguages($languages, $publicationId, $deleteFirst = true) {
		$languageDao = DAORegistry::getDAO('SubmissionLanguageDAO');
		$submissionLanguageEntryDao = DAORegistry::getDAO('SubmissionLanguageEntryDAO');
		$currentLanguages = $this->build($publicationId);

		if ($deleteFirst) {
			$existingEntries = $languageDao->enumerate($currentLanguages->getId(), CONTROLLED_VOCAB_SUBMISSION_LANGUAGE);

			foreach ($existingEntries as $id => $entry) {
				$entry = trim($entry);
				$submissionLanguageEntryDao->deleteObjectById($id);
			}
		}
		if (is_array($languages)) { // localized, array of arrays

			foreach ($languages as $locale => $list) {
				if (is_array($list)) {
					$list = array_unique($list); // Remove any duplicate Languages
					$i = 1;
					foreach ($list as $language) {
						$languageEntry = $submissionLanguageEntryDao->newDataObject();
						$languageEntry->setControlledVocabId($currentLanguages->getID());
						$languageEntry->setLanguage(urldecode($language), $locale);
						$languageEntry->setSequence($i);
						$i++;
						$submissionLanguageEntryDao->insertObject($languageEntry);
					}
				}
			}
		}
	}
}


