<?php

/**
 * @file classes/metadata/OpenUrlJournalSchema.inc.php
 *
 * Copyright (c) 2000-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OpenUrlJournalSchema
 * @ingroup metadata
 * @see OpenUrlJournalBookBaseSchema
 *
 * @brief Class that provides meta-data properties of the
 *  OpenURL journal 1.0 standard.
 */

// $Id$

import('metadata.OpenUrlJournalBookBaseSchema');

class OpenUrlJournalSchema extends OpenUrlJournalBookBaseSchema {
	/**
	 * Constructor
	 */
	function OpenUrlJournalSchema() {
		// Add meta-data properties that only appear in the OpenURL journal standard
		$this->addProperty(new MetadataProperty('jtitle'));
		$this->addProperty(new MetadataProperty('stitle')); // Short title
		$this->addProperty(new MetadataProperty('chron'));  // Enumeration or chronology in not-normalized form, e.g. "1st quarter"
		$this->addProperty(new MetadataProperty('ssn'));    // Season
		$this->addProperty(new MetadataProperty('quarter'));
		$this->addProperty(new MetadataProperty('volume'));
		$this->addProperty(new MetadataProperty('part'));   // A special subdivision of a volume or the highest level division of the journal
		$this->addProperty(new MetadataProperty('issue'));
		$this->addProperty(new MetadataProperty('artnum')); // Number assigned by the publisher
		$this->addProperty(new MetadataProperty('eissn'));
		$this->addProperty(new MetadataProperty('coden'));
		$this->addProperty(new MetadataProperty('sici'));
	}
}
?>