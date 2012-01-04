<?php

/**
 * @file classes/user/InterestDAO.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
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
		return parent::build(CONTROLLED_VOCAB_INTEREST, ROLE_ID_REVIEWER, $userId);
	}
	
	function getInterests($userId, $withQuotes = true) {
		$interests = $this->build($userId);
		$interestEntryDao =& DAORegistry::getDAO('InterestEntryDAO');
	 	$userInterests = $interestEntryDao->getByControlledVocabId($interests->getId());
	 	
	 	$returner = array();
	 	while ($interest =& $userInterests->next()) {
	 		if ($withQuotes) {
		 		$returner[] = "\"" . $interest->getInterest() . "\"";
		 	} else {
				$returner[] = $interest->getInterest();
		 	}
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
			$interests[] = "\"" . $result->fields[0] . "\"";
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
			'SELECT controlled_vocab_id
			 FROM controlled_vocab_entries cve
			 INNER JOIN controlled_vocab_entry_settings cves ON cve.controlled_vocab_entry_id = cves.controlled_vocab_entry_id
			 WHERE cves.setting_name = ? AND cves.setting_value = ?',
			array('interest', $interest)
		);

		$returner = array();
		while (!$result->EOF) {
			$row = $result->GetRowAssoc(false);
			$returner[] = $row['controlled_vocab_id'];
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
		
		foreach ($interests as $interest) {
			$interestEntry = $interestEntryDao->newDataObject();
			$interestEntry->setControlledVocabId($currentInterests->getId());
			$interestEntry->setInterest($interest);
			$interestEntryDao->insertObject($interestEntry);	
		}
	}

}

?>
