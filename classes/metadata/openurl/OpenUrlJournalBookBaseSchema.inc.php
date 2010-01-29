<?php

/**
 * @file classes/metadata/OpenUrlJournalBookBaseSchema.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
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

class OpenUrlJournalBookBaseSchema extends OpenUrlBaseSchema {
	/**
	 * Constructor
	 */
	function OpenUrlJournalBookBaseSchema() {
		// Add meta-data properties common to the OpenURL book/journal standard
		$this->addProperty(new MetadataProperty('aucorp'));   // Organization or corporation that is the author or creator
		$this->addProperty(new MetadataProperty('atitle'));
		$this->addProperty(new MetadataProperty('spage', array(), METADATA_PROPERTY_TYPE_INTEGER));
		$this->addProperty(new MetadataProperty('epage', array(), METADATA_PROPERTY_TYPE_INTEGER));
		$this->addProperty(new MetadataProperty('pages', array(), METADATA_PROPERTY_TYPE_INTEGER));
		$this->addProperty(new MetadataProperty('issn'));
		$this->addProperty(new MetadataProperty('genre'));
		// FIXME: implement genre as controlled vocabulary.
		// Allowed values in the journal schema: "journal", "issue", "article", "proceeding", "conference", "preprint", "unknown"
		// Allowed values in the book schema: "book", "bookitem", "conference", "proceeding", "report", "document", "unknown"
	}
}
?>