<?php

/**
 * @file classes/submission/SubmissionLanguageDAO.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
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
	 * @param $assocType int DO NOT USE: For <3.1 to 3.x migration pkp/pkp-lib#3572 pkp/pkp-lib#6213
	 * @return ControlledVocab
	 */
	function build($publicationId, $assocType = ASSOC_TYPE_PUBLICATION) {
		// may return an array of ControlledVocabs
		return parent::_build(CONTROLLED_VOCAB_SUBMISSION_LANGUAGE, $assocType, $publicationId);
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
	 * @param $assocType int DO NOT USE: For <3.1 to 3.x migration pkp/pkp-lib#6213
	 * @return array
	 */
	function getLanguages($publicationId, $locales = [], $assocType = ASSOC_TYPE_PUBLICATION) {
		$result = [];

		$languages = $this->build($publicationId, $assocType);
		$submissionLanguageEntryDao = DAORegistry::getDAO('SubmissionLanguageEntryDAO'); /* @var $submissionLanguageEntryDao SubmissionLanguageEntryDAO */
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
	 * @param $assocType int DO NOT USE: For <3.1 to 3.x migration pkp/pkp-lib#3572 pkp/pkp-lib#6213
	 * @return int
	 */
	function insertLanguages($languages, $publicationId, $deleteFirst = true, $assocType = ASSOC_TYPE_PUBLICATION) {
		$languageDao = DAORegistry::getDAO('SubmissionLanguageDAO'); /* @var $languageDao SubmissionLanguageDAO */
		$submissionLanguageEntryDao = DAORegistry::getDAO('SubmissionLanguageEntryDAO'); /* @var $submissionLanguageEntryDao SubmissionLanguageEntryDAO */
		$currentLanguages = $this->build($publicationId, $assocType);

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
						$languageEntry->setControlledVocabId($currentLanguages->getId());
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


