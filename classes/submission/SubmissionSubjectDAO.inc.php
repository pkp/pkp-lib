<?php

/**
 * @file classes/submission/SubmissionSubjectDAO.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionSubjectDAO
 * @ingroup submission
 * @see Submission
 *
 * @brief Operations for retrieving and modifying a submission's assigned subjects
 */

import('lib.pkp.classes.controlledVocab.ControlledVocabDAO');

define('CONTROLLED_VOCAB_SUBMISSION_SUBJECT', 'submissionSubject');

class SubmissionSubjectDAO extends ControlledVocabDAO {

	/**
	 * Build/fetch and return a controlled vocabulary for subjects.
	 * @param $publicationId int
	 * @return ControlledVocab
	 */
	function build($publicationId) {
		// may return an array of ControlledVocabs
		return parent::_build(CONTROLLED_VOCAB_SUBMISSION_SUBJECT, ASSOC_TYPE_PUBLICATION, $publicationId);
	}

	/**
	 * Get the list of localized additional fields to store.
	 * @return array
	 */
	function getLocaleFieldNames() {
		return array('submissionSubject');
	}

	/**
	 * Get Subjects for a submission.
	 * @param $publicationId int
	 * @param $locales array
	 * @return array
	 */
	function getSubjects($publicationId, $locales = []) {
		$result = [];

		$subjects = $this->build($publicationId);
		$submissionSubjectEntryDao = DAORegistry::getDAO('SubmissionSubjectEntryDAO');
		$submissionSubjects = $submissionSubjectEntryDao->getByControlledVocabId($subjects->getId());
		while ($subjectEntry = $submissionSubjects->next()) {
			$subject = $subjectEntry->getSubject();
			foreach ($subject as $locale => $value) {
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
	 * Get an array of all of the submission's Subjects
	 * @return array
	 */
	function getAllUniqueSubjects() {
		$subjects = array();

		$result = $this->retrieve(
			'SELECT DISTINCT setting_value FROM controlled_vocab_entry_settings WHERE setting_name = ?', CONTROLLED_VOCAB_SUBMISSION_SUBJECT
		);

		while (!$result->EOF) {
			$subjects[] = $result->fields[0];
			$result->MoveNext();
		}

		$result->Close();
		return $subjects;
	}

	/**
	 * Add an array of subjects
	 * @param $subjects array
	 * @param $publicationId int
	 * @param $deleteFirst boolean
	 * @return int
	 */
	function insertSubjects($subjects, $publicationId, $deleteFirst = true) {
		$subjectDao = DAORegistry::getDAO('SubmissionSubjectDAO');
		$submissionSubjectEntryDao = DAORegistry::getDAO('SubmissionSubjectEntryDAO');
		$currentSubjects = $this->build($publicationId);

		if ($deleteFirst) {
			$existingEntries = $subjectDao->enumerate($currentSubjects->getId(), CONTROLLED_VOCAB_SUBMISSION_SUBJECT);

			foreach ($existingEntries as $id => $entry) {
				$entry = trim($entry);
				$submissionSubjectEntryDao->deleteObjectById($id);
			}
		}
		if (is_array($subjects)) { // localized, array of arrays

			foreach ($subjects as $locale => $list) {
				if (is_array($list)) {
					$list = array_unique($list); // Remove any duplicate Subjects
					$i = 1;
					foreach ($list as $subject) {
						$subjectEntry = $submissionSubjectEntryDao->newDataObject();
						$subjectEntry->setControlledVocabId($currentSubjects->getID());
						$subjectEntry->setSubject(urldecode($subject), $locale);
						$subjectEntry->setSequence($i);
						$i++;
						$submissionSubjectEntryDao->insertObject($subjectEntry);
					}
				}
			}
		}
	}
}


