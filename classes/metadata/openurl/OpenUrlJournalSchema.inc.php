<?php

/**
 * @file classes/metadata/openurl/OpenUrlJournalSchema.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OpenUrlJournalSchema
 * @ingroup metadata_openurl
 * @see OpenUrlJournalBookBaseSchema
 *
 * @brief Class that provides meta-data properties of the
 *  OpenURL journal 1.0 standard.
 */


import('lib.pkp.classes.metadata.openurl.OpenUrlJournalBookBaseSchema');

define('OPENURL_GENRE_JOURNAL', 'journal');
define('OPENURL_GENRE_ISSUE', 'issue');
define('OPENURL_GENRE_ARTICLE', 'article');
define('OPENURL_GENRE_PREPRINT', 'preprint');

class OpenUrlJournalSchema extends OpenUrlJournalBookBaseSchema {
	/**
	 * Constructor
	 */
	function OpenUrlJournalSchema() {
		parent::OpenUrlJournalBookBaseSchema('openurl-1.0-journal');

		// Add meta-data properties that only appear in the OpenURL journal standard
		$this->addProperty('jtitle');
		$this->addProperty('stitle'); // Short title
		$this->addProperty('chron');  // Enumeration or chronology in not-normalized form, e.g. "1st quarter"
		$this->addProperty('ssn');    // Season
		$this->addProperty('quarter');
		$this->addProperty('volume');
		$this->addProperty('part');   // A special subdivision of a volume or the highest level division of the journal
		$this->addProperty('issue');
		$this->addProperty('artnum'); // Number assigned by the publisher
		$this->addProperty('eissn');
		$this->addProperty('coden');
		$this->addProperty('sici');
		$this->addProperty('genre', array(METADATA_PROPERTY_TYPE_VOCABULARY => 'openurl10-journal-genres'));
	}
}
?>