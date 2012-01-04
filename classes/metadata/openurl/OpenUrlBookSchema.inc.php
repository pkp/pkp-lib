<?php

/**
 * @file classes/metadata/OpenUrlBookSchema.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OpenUrlBookSchema
 * @ingroup metadata_openurl
 * @see OpenUrlJournalBookBaseSchema
 *
 * @brief Class that provides meta-data properties of the
 *  OpenURL 1.0 book standard.
 */

// $Id$

import('metadata.openurl.OpenUrlJournalBookBaseSchema');

define('OPENURL_GENRE_BOOK', 'book');
define('OPENURL_GENRE_BOOKITEM', 'bookitem');
define('OPENURL_GENRE_REPORT', 'report');
define('OPENURL_GENRE_DOCUMENT', 'document');

class OpenUrlBookSchema extends OpenUrlJournalBookBaseSchema {
	/**
	 * Constructor
	 */
	function OpenUrlBookSchema() {
		$this->setName('openurl-1.0-book');

		parent::OpenUrlJournalBookBaseSchema();

		// Add meta-data properties that only appear in the OpenURL book standard
		$citation = array(ASSOC_TYPE_CITATION);
		$this->addProperty(new MetadataProperty('btitle', $citation));
		$this->addProperty(new MetadataProperty('place', $citation)); // Place of publication
		$this->addProperty(new MetadataProperty('pub', $citation));   // Publisher
		$this->addProperty(new MetadataProperty('edition', $citation));
		$this->addProperty(new MetadataProperty('tpages', $citation));
		$this->addProperty(new MetadataProperty('series', $citation)); // The title of a series in which the book or document was issued.
		$this->addProperty(new MetadataProperty('bici', $citation));
	}
}
?>