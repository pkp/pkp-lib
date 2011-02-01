<?php

/**
 * @file classes/user/InterestDAO.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class InterestDAO
 * @ingroup user
 * @see User
 *
 * @brief Operations for retrieving and modifying a user's review interests.
 */

// $Id$

import('lib.pkp.classes.controlledVocab.ControlledVocabDAO');

define('CONTROLLED_VOCAB_INTEREST', 'interest');

class InterestDAO extends ControlledVocabDAO {

	function build($userId) {
		return parent::build(CONTROLLED_VOCAB_INTEREST, ASSOC_TYPE_USER, $userId);
	}

	function getInterests($userId) {
		$interests = $this->build($userId);
		$interestEntryDao =& DAORegistry::getDAO('InterestEntryDAO');
	 	$userInterests = $interestEntryDao->getByControlledVocabId($interests->getId());

	 	$returner = array();
	 	while ($interest =& $userInterests->next()) {
	 		$returner[] = $interest->getInterest();
	 		unset($interest);
	 	}

	 	return $returner;
	}

	/**
	 * Get an array of all user's interests
	 * @return array
	 */
	function getAllUniqueInterests() {
		$interests = array();

		$result =& $this->retrieve(
			'SELECT DISTINCT setting_value FROM controlled_vocab_entry_settings WHERE setting_name = ?', 'interest'
		);

		while (!$result->EOF) {
			$interests[] = $result->fields[0];
			$result->moveNext();
		}

		$result->Close();
		unset($result);

		return $interests;
	}

	/**
	 * Get an array of userId's that have a given interest
	 * @param $content string
	 * @return array
	 */
	function getUserIdsByInterest($interest) {
		$result =& $this->retrieve(
			'SELECT assoc_id
			 FROM controlled_vocabs cv
			 LEFT JOIN controlled_vocab_entries cve ON cv.controlled_vocab_id = cve.controlled_vocab_id
			 INNER JOIN controlled_vocab_entry_settings cves ON cve.controlled_vocab_entry_id = cves.controlled_vocab_entry_id
			 WHERE cves.setting_name = ? AND cves.setting_value = ?',
			array('interest', $interest)
		);

		$returner = array();
		while (!$result->EOF) {
			$row = $result->GetRowAssoc(false);
			$returner[] = $row['assoc_id'];
			$result->MoveNext();
		}
		$result->Close();
		return $returner;


	}

	/**
	 * Add an array of interests
	 * @param $interest array
	 * @param $userId int
	 * @param $deleteFirst boolean
	 * @return int
	 */
	function insertInterests($interests, $userId, $deleteFirst = true) {
		$interestDao =& DAORegistry::getDAO('InterestDAO');
		$interestEntryDao =& DAORegistry::getDAO('InterestEntryDAO');
		$currentInterests = $this->build($userId);

		if ($deleteFirst) {
			$existingEntries = $interestDao->enumerate($currentInterests->getId(), 'interest');

			foreach ($existingEntries as $id => $entry) {
				$interestEntryDao->deleteObjectById($id);
			}
		}

		$interests = array_unique($interests); // Remove any duplicate interests that weren't caught by the JS validator
		foreach ($interests as $interest) {
			$interestEntry = $interestEntryDao->newDataObject();
			$interestEntry->setControlledVocabId($currentInterests->getId());
			$interestEntry->setInterest($interest);
			$interestEntryDao->insertObject($interestEntry);
		}
	}

}

?>
