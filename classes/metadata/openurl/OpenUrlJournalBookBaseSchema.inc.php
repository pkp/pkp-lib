<?php

/**
 * @file classes/metadata/OpenUrlJournalBookBaseSchema.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OpenUrlJournalBookBaseSchema
 * @ingroup metadata_openurl
 * @see OpenUrlBaseSchema
 *
 * @brief Class that provides meta-data properties common to the
 *  journal and book variants of the OpenURL 1.0 standard.
 */

// $Id$

import('metadata.openurl.OpenUrlBaseSchema');

define('OPENURL_GENRE_CONFERENCE', 'conference');
define('OPENURL_GENRE_PROCEEDING', 'proceeding');
define('OPENURL_GENRE_UNKNOWN', 'unknown');

class OpenUrlJournalBookBaseSchema extends OpenUrlBaseSchema {
	/**
	 * Constructor
	 */
	function OpenUrlJournalBookBaseSchema() {
		parent::OpenUrlBaseSchema();

		// Add meta-data properties common to the OpenURL book/journal standard
		$citation = array(ASSOC_TYPE_CITATION);
		$this->addProperty(new MetadataProperty('aucorp', $citation));   // Organization or corporation that is the author or creator
		$this->addProperty(new MetadataProperty('atitle', $citation));
		$this->addProperty(new MetadataProperty('spage', $citation, METADATA_PROPERTY_TYPE_INTEGER));
		$this->addProperty(new MetadataProperty('epage', $citation, METADATA_PROPERTY_TYPE_INTEGER));
		$this->addProperty(new MetadataProperty('pages', $citation));
		$this->addProperty(new MetadataProperty('issn', $citation));
		$this->addProperty(new MetadataProperty('genre', $citation));
		// FIXME: implement genre as controlled vocabulary.
		// Allowed values in the journal schema: "journal", "issue", "article", "proceeding", "conference", "preprint", "unknown"
		// Allowed values in the book schema: "book", "bookitem", "conference", "proceeding", "report", "document", "unknown"
	}
}
?>