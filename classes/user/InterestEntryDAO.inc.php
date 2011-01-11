<?php

/**
 * @file classes/user/InterestsEntryDAO.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class InterestsEntryDAO
 * @ingroup user
 * @see User
 *
 * @brief Operations for retrieving and modifying a user's review interests.
 */

// $Id$


import('lib.pkp.classes.user.InterestEntry');
import('lib.pkp.classes.controlledVocab.ControlledVocabEntryDAO');

class InterestEntryDAO extends ControlledVocabEntryDAO {
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
}

?>
