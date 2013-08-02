<?php

/**
 * @file classes/user/InterestEntryDAO.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class InterestsEntryDAO
 * @ingroup user
 * @see User
 *
 * @brief Operations for retrieving and modifying a user's review interests.
 */


import('lib.pkp.classes.user.InterestEntry');
import('lib.pkp.classes.controlledVocab.ControlledVocabEntryDAO');

class InterestEntryDAO extends ControlledVocabEntryDAO {
	/**
	 * Constructor
	 */
	function InterestEntryDAO() {
		parent::ControlledVocabEntryDAO();
	}

	/**
	 * Construct a new data object corresponding to this DAO.
	 * @return PaperTypeEntry
	 */
	function newDataObject() {
		return new InterestEntry();
	}

	/**
	 * Get the list of non-localized additional fields to store.
	 * @return array
	 */
	function getAdditionalFieldNames() {
		return array('interest');
	}

	/**
	 * Retrieve an iterator of controlled vocabulary entries matching a
	 * particular controlled vocabulary ID.
	 * @param $controlledVocabId int
	 * @return object DAOResultFactory containing matching CVE objects
	 */
	function getByControlledVocabId($controlledVocabId, $rangeInfo = null) {
		$result = $this->retrieveRange(
			'SELECT cve.* FROM controlled_vocab_entries cve, user_interests ui WHERE cve.controlled_vocab_id = ? AND ui.controlled_vocab_entry_id = cve.controlled_vocab_entry_id ORDER BY seq',
			array((int) $controlledVocabId),
			$rangeInfo
		);
		return new DAOResultFactory($result, $this, '_fromRow');
	}
}

?>
