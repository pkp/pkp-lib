<?php

/**
 * @file classes/submission/SubmissionDisciplineDAO.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionDisciplineDAO
 * @ingroup submission
 * @see Submission
 *
 * @brief Operations for retrieving and modifying a submission's assigned
 * disciplines
 */

import('lib.pkp.classes.controlledVocab.ControlledVocabDAO');

define('CONTROLLED_VOCAB_SUBMISSION_DISCIPLINE', 'submissionDiscipline');

class SubmissionDisciplineDAO extends ControlledVocabDAO {

	/**
	 * Build/fetch a publication's discipline controlled vocabulary.
	 * @pararm $publicationId int
	 * @return ControlledVocabulary
	 */
	function build($publicationId) {
		return parent::_build(CONTROLLED_VOCAB_SUBMISSION_DISCIPLINE, ASSOC_TYPE_PUBLICATION, $publicationId);
	}

	/**
	 * Get the list of localized additional fields to store.
	 * @return array
	 */
	function getLocaleFieldNames() {
		return array('submissionDiscipline');
	}

	/**
	 * Get disciplines for a submission.
	 * @param $publicationId int
	 * @param $locales array
	 * @return array
	 */
	function getDisciplines($publicationId, $locales = []) {
		$result = [];

		$disciplines = $this->build($publicationId);
		$submissionDisciplineEntryDao = DAORegistry::getDAO('SubmissionDisciplineEntryDAO');
		$submissionDisciplines = $submissionDisciplineEntryDao->getByControlledVocabId($disciplines->getId());
		while ($disciplineEntry = $submissionDisciplines->next()) {
			$discipline = $disciplineEntry->getDiscipline();
			foreach ($discipline as $locale => $value) {
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
	 * Get an array of all of the submission's disciplines
	 * @return array
	 */
	function getAllUniqueDisciplines() {
		$disciplines = array();

		$result = $this->retrieve(
			'SELECT DISTINCT setting_value FROM controlled_vocab_entry_settings WHERE setting_name = ?', CONTROLLED_VOCAB_SUBMISSION_DISCIPLINE
		);

		while (!$result->EOF) {
			$disciplines[] = $result->fields[0];
			$result->MoveNext();
		}

		$result->Close();
		return $disciplines;
	}

	/**
	 * Add an array of disciplines
	 * @param $disciplines array
	 * @param $publicationId int
	 * @param $deleteFirst boolean
	 * @return int
	 */
	function insertDisciplines($disciplines, $publicationId, $deleteFirst = true) {
		$disciplineDao = DAORegistry::getDAO('SubmissionDisciplineDAO');
		$submissionDisciplineEntryDao = DAORegistry::getDAO('SubmissionDisciplineEntryDAO');
		$currentDisciplines = $this->build($publicationId);

		if ($deleteFirst) {
			$existingEntries = $disciplineDao->enumerate($currentDisciplines->getId(), CONTROLLED_VOCAB_SUBMISSION_DISCIPLINE);

			foreach ($existingEntries as $id => $entry) {
				$entry = trim($entry);
				$submissionDisciplineEntryDao->deleteObjectById($id);
			}
		}
		if (is_array($disciplines)) { // localized, array of arrays

			foreach ($disciplines as $locale => $list) {
				if (is_array($list)) {
					$list = array_unique($list); // Remove any duplicate keywords
					$i = 1;
					foreach ($list as $discipline) {
						$disciplineEntry = $submissionDisciplineEntryDao->newDataObject();
						$disciplineEntry->setControlledVocabId($currentDisciplines->getID());
						$disciplineEntry->setDiscipline(urldecode($discipline), $locale);
						$disciplineEntry->setSequence($i);
						$i++;
						$submissionDisciplineEntryDao->insertObject($disciplineEntry);
					}
				}
			}
		}
	}
}


