<?php

/**
 * @file classes/user/InterestManager.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class InterestManager
 * @ingroup user
 * @see InterestDAO
 * @brief Handle user interest functions.
 */

class InterestManager {
	/**
	 * Constructor.
	 */
	function InterestManager() {
	}

	/**
	 * Insert an array of keywords into the controlled vocabulary structure
	 * @param $userId int
	 * @param $interests array
	 * @param $interestsTextOnly string Will be populated if the user's javascript is disabled and is using the old-style interest field
	 */
	function insertInterests($userId, $interests = array(), $interestsTextOnly = null) {
		if(isset($interestsTextOnly)) {
			// If JS is disabled, this will be the input to read
			$interestsTextOnly = explode(",", $interestsTextOnly);
		} else $interestsTextOnly = null;
		if ($interestsTextOnly && empty($interests)) {
			$interests = $interestsTextOnly;
		} elseif (isset($interests) && !is_array($interests)) {
			$interests = array($interests);
		}

		$interestDao =& DAORegistry::getDAO('InterestDAO');
		$interestDao->insertInterests($interests, $userId);
	}

}

?>
