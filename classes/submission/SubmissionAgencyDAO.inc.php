<?php

/**
 * @file classes/submission/SubmissionAgencyDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionAgencyDAO
 * @ingroup submission
 * @see Submission
 *
 * @brief Operations for retrieving and modifying a submission's assigned agencies
 */

import('lib.pkp.classes.controlledVocab.ControlledVocabDAO');

define('CONTROLLED_VOCAB_SUBMISSION_AGENCY', 'submissionAgency');

class SubmissionAgencyDAO extends ControlledVocabDAO {

	/**
	 * Build/fetch and return a controlled vocabulary for agencies.
	 * @param $publicationId int
	 * @param $assocType int DO NOT USE: For <3.1 to 3.x migration pkp/pkp-lib#3572 pkp/pkp-lib#6213
	 * @return ControlledVocab
	 */
	function build($publicationId, $assocType = ASSOC_TYPE_PUBLICATION) {
		return parent::_build(CONTROLLED_VOCAB_SUBMISSION_AGENCY, $assocType, $publicationId);
	}

	/**
	 * Get the list of localized additional fields to store.
	 * @return array
	 */
	function getLocaleFieldNames() {
		return array('submissionAgency');
	}

	/**
	 * Get agencies for a specified submission ID.
	 * @param $publicationId int
	 * @param $locales array
	 * @param $assocType int DO NOT USE: For <3.1 to 3.x migration pkp/pkp-lib#6213
	 * @return array
	 */
	function getAgencies($publicationId, $locales = [], $assocType = ASSOC_TYPE_PUBLICATION) {
		$result = [];

		$agencies = $this->build($publicationId, $assocType);
		$submissionAgencyEntryDao = DAORegistry::getDAO('SubmissionAgencyEntryDAO'); /* @var $submissionAgencyEntryDao SubmissionAgencyEntryDAO */
		$submissionAgencies = $submissionAgencyEntryDao->getByControlledVocabId($agencies->getId());
		while ($agencyEntry = $submissionAgencies->next()) {
			$agency = $agencyEntry->getAgency();
			foreach ($agency as $locale => $value) {
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
	 * Get an array of all of the submission's agencies
	 * @return array
	 */
	function getAllUniqueAgencies() {
		$result = $this->retrieve('SELECT DISTINCT setting_value FROM controlled_vocab_entry_settings WHERE setting_name = ?', [CONTROLLED_VOCAB_SUBMISSION_AGENCY]);

		$agencies = [];
		foreach ($result as $row) {
			$agencies[] = $row->setting_value;
		}
		return $agencies;
	}

	/**
	 * Add an array of agencies
	 * @param $agencies array List of agencies.
	 * @param $publicationId int Submission ID.
	 * @param $deleteFirst boolean True iff existing agencies should be removed first.
	 * @param $assocType int DO NOT USE: For <3.1 to 3.x migration pkp/pkp-lib#3572 pkp/pkp-lib#6213
	 * @return int
	 */
	function insertAgencies($agencies, $publicationId, $deleteFirst = true, $assocType = ASSOC_TYPE_PUBLICATION) {
		$agencyDao = DAORegistry::getDAO('SubmissionAgencyDAO'); /* @var $agencyDao SubmissionAgencyDAO */
		$submissionAgencyEntryDao = DAORegistry::getDAO('SubmissionAgencyEntryDAO'); /* @var $submissionAgencyEntryDao SubmissionAgencyEntryDAO */
		$currentAgencies = $this->build($publicationId, $assocType);

		if ($deleteFirst) {
			$existingEntries = $agencyDao->enumerate($currentAgencies->getId(), CONTROLLED_VOCAB_SUBMISSION_AGENCY);

			foreach ($existingEntries as $id => $entry) {
				$entry = trim($entry);
				$submissionAgencyEntryDao->deleteObjectById($id);
			}
		}
		if (is_array($agencies)) { // localized, array of arrays

			foreach ($agencies as $locale => $list) {
				if (is_array($list)) {
					$list = array_unique($list); // Remove any duplicate keywords
					$i = 1;
					foreach ($list as $agency) {
						$agencyEntry = $submissionAgencyEntryDao->newDataObject();
						$agencyEntry->setControlledVocabId($currentAgencies->getId());
						$agencyEntry->setAgency(urldecode($agency), $locale);
						$agencyEntry->setSequence($i);
						$i++;
						$submissionAgencyEntryDao->insertObject($agencyEntry);
					}
				}
			}
		}
	}
}


