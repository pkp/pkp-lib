<?php

/**
 * @file classes/submission/SubmissionLanguage.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionLanguage
 * @ingroup submission
 * @see SubmissionLanguageEntryDAO
 *
 * @brief Basic class describing a submission language
 */

import('lib.pkp.classes.controlledVocab.ControlledVocabEntry');

class SubmissionLanguage extends ControlledVocabEntry {
	//
	// Get/set methods
	//

	/**
	 * Get the language
	 * @return string
	 */
	function getLanguage() {
		return $this->getData('submissionLanguage');
	}

	/**
	 * Set the language text
	 * @param language string
	 * @param locale string
	 */
	function setLanguage($language, $locale) {
		$this->setData('submissionLanguage', $language, $locale);
	}

	/**
	 * @copydoc ControlledVocabEntry::getLocaleMetadataFieldNames()
	 */
	function getLocaleMetadataFieldNames() {
		return array('submissionLanguage');
	}
}

