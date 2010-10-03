<?php

/**
 * @file plugins/metadata/openurl10/OpenUrl10BookSchema.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OpenUrl10BookSchema
 * @ingroup metadata_openurl
 * @see OpenUrl10JournalBookBaseSchema
 *
 * @brief Class that provides meta-data properties of the
 *  OpenURL 1.0 book standard.
 */


import('lib.pkp.plugins.metadata.openurl10.schema.OpenUrl10JournalBookBaseSchema');

define('OPENURL_GENRE_BOOK', 'book');
define('OPENURL_GENRE_BOOKITEM', 'bookitem');
define('OPENURL_GENRE_REPORT', 'report');
define('OPENURL_GENRE_DOCUMENT', 'document');

class OpenUrl10BookSchema extends OpenUrl10JournalBookBaseSchema {
	/**
	 * Constructor
	 */
	function OpenUrl10BookSchema() {
		parent::OpenUrl10JournalBookBaseSchema('openurl-1.0-book');

		// Add meta-data properties that only appear in the OpenURL book standard
		$this->addProperty('btitle');
		$this->addProperty('place'); // Place of publication
		$this->addProperty('pub');   // Publisher
		$this->addProperty('edition');
		$this->addProperty('tpages');
		$this->addProperty('series'); // The title of a series in which the book or document was issued.
		$this->addProperty('bici');
		$this->addProperty('genre', array(METADATA_PROPERTY_TYPE_VOCABULARY => 'openurl10-book-genres'));
	}
}
?>