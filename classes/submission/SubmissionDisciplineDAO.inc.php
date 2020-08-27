<?php

/**
 * @file classes/submission/SubmissionDisciplineDAO.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
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
	 * @param $publicationId int
	 * @param $assocType int DO NOT USE: For <3.1 to 3.x migration pkp/pkp-lib#3572 pkp/pkp-lib#6213
	 * @return ControlledVocabulary
	 */
	function build($publicationId, $assocType = ASSOC_TYPE_PUBLICATION) {
		return parent::_build(CONTROLLED_VOCAB_SUBMISSION_DISCIPLINE, $assocType, $publicationId);
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
	 * @param $assocType int DO NOT USE: For <3.1 to 3.x migration pkp/pkp-lib#6213
	 * @return array
	 */
	function getDisciplines($publicationId, $locales = [], $assocType = ASSOC_TYPE_PUBLICATION) {
		$result = [];

		$disciplines = $this->build($publicationId, $assocType);
		$submissionDisciplineEntryDao = DAORegistry::getDAO('SubmissionDisciplineEntryDAO'); /* @var $submissionDisciplineEntryDao SubmissionDisciplineEntryDAO */
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
	 * @param $assocType int DO NOT USE: For <3.1 to 3.x migration pkp/pkp-lib#3572 pkp/pkp-lib#6213
	 * @return int
	 */
	function insertDisciplines($disciplines, $publicationId, $deleteFirst = true, $assocType = ASSOC_TYPE_PUBLICATION) {
		$disciplineDao = DAORegistry::getDAO('SubmissionDisciplineDAO'); /* @var $disciplineDao SubmissionDisciplineDAO */
		$submissionDisciplineEntryDao = DAORegistry::getDAO('SubmissionDisciplineEntryDAO'); /* @var $submissionDisciplineEntryDao SubmissionDisciplineEntryDAO */
		$currentDisciplines = $this->build($publicationId, $assocType);

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
						$disciplineEntry->setControlledVocabId($currentDisciplines->getId());
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


