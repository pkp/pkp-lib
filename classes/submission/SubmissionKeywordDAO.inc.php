<?php

/**
 * @file classes/submission/SubmissionKeywordDAO.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionKeywordDAO
 * @ingroup submission
 * @see Submission
 *
 * @brief Operations for retrieving and modifying a submission's assigned keywords
 */

import('lib.pkp.classes.controlledVocab.ControlledVocabDAO');

define('CONTROLLED_VOCAB_SUBMISSION_KEYWORD', 'submissionKeyword');

class SubmissionKeywordDAO extends ControlledVocabDAO {

	/**
	 * Build/fetch and return a controlled vocabulary for keywords.
	 * @param $publicationId int
	 * @return ControlledVocab
	 */
	function build($publicationId) {
		// may return an array of ControlledVocabs
		return parent::_build(CONTROLLED_VOCAB_SUBMISSION_KEYWORD, ASSOC_TYPE_PUBLICATION, $publicationId);
	}

	/**
	 * Get the list of localized additional fields to store.
	 * @return array
	 */
	function getLocaleFieldNames() {
		return array('submissionKeyword');
	}

	/**
	 * Get keywords for a submission.
	 * @param $publicationId int
	 * @param $locales array
	 * @return array
	 */
	function getKeywords($publicationId, $locales = []) {
		$result = [];

		$keywords = $this->build($publicationId);
		$submissionKeywordEntryDao = DAORegistry::getDAO('SubmissionKeywordEntryDAO');
		$submissionKeywords = $submissionKeywordEntryDao->getByControlledVocabId($keywords->getId());
		while ($keywordEntry = $submissionKeywords->next()) {
			$keyword = $keywordEntry->getKeyword();
			foreach ($keyword as $locale => $value) {
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
	 * Get an array of all of the submission's keywords
	 * @return array
	 */
	function getAllUniqueKeywords() {
		$keywords = array();

		$result = $this->retrieve(
			'SELECT DISTINCT setting_value FROM controlled_vocab_entry_settings WHERE setting_name = ?', CONTROLLED_VOCAB_SUBMISSION_KEYWORD
		);

		while (!$result->EOF) {
			$keywords[] = $result->fields[0];
			$result->MoveNext();
		}

		$result->Close();
		return $keywords;
	}

	/**
	 * Add an array of keywords
	 * @param $keywords array
	 * @param $publicationId int
	 * @param $deleteFirst boolean
	 * @return int
	 */
	function insertKeywords($keywords, $publicationId, $deleteFirst = true) {
		$keywordDao = DAORegistry::getDAO('SubmissionKeywordDAO');
		$submissionKeywordEntryDao = DAORegistry::getDAO('SubmissionKeywordEntryDAO');

		if ($deleteFirst) {
			$currentKeywords = $this->deleteByPublicationId($publicationId);
		} else {
			$currentKeywords = $this->build($publicationId);
		}
		if (is_array($keywords)) { // localized, array of arrays

			foreach ($keywords as $locale => $list) {
				if (is_array($list)) {
					$list = array_unique($list); // Remove any duplicate keywords
					$i = 1;
					foreach ($list as $keyword) {
						$keywordEntry = $submissionKeywordEntryDao->newDataObject();
						$keywordEntry->setControlledVocabId($currentKeywords->getID());
						$keywordEntry->setKeyword(urldecode($keyword), $locale);
						$keywordEntry->setSequence($i);
						$i++;
						$submissionKeywordEntryDao->insertObject($keywordEntry);
					}
				}
			}
		}
	}

	/**
	 * Delete keywords by publication ID
	 *
	 * @param int $publicationid
	 * @return int|array Controlled Vocab
	 */
	public function deleteByPublicationId($publicationId) {
		$keywordDao = DAORegistry::getDAO('SubmissionKeywordDAO');
		$submissionKeywordEntryDao = DAORegistry::getDAO('SubmissionKeywordEntryDAO');
		$currentKeywords = $this->build($publicationId);

		$existingEntries = $keywordDao->enumerate($currentKeywords->getId(), CONTROLLED_VOCAB_SUBMISSION_KEYWORD);
		foreach ($existingEntries as $id => $entry) {
			$entry = trim($entry);
			$entryObj = $submissionKeywordEntryDao->getById($id);
			$submissionKeywordEntryDao->deleteObjectById($id);
		}

		return $currentKeywords;
	}

}


