<?php

/**
 * @file classes/metadata/openurl/OpenUrlBookSchema.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OpenUrlBookSchema
 * @ingroup metadata_openurl
 * @see OpenUrlJournalBookBaseSchema
 *
 * @brief Class that provides meta-data properties of the
 *  OpenURL 1.0 book standard.
 */


import('lib.pkp.classes.metadata.openurl.OpenUrlJournalBookBaseSchema');

define('OPENURL_GENRE_BOOK', 'book');
define('OPENURL_GENRE_BOOKITEM', 'bookitem');
define('OPENURL_GENRE_REPORT', 'report');
define('OPENURL_GENRE_DOCUMENT', 'document');

class OpenUrlBookSchema extends OpenUrlJournalBookBaseSchema {
	/**
	 * Constructor
	 */
	function OpenUrlBookSchema() {
		parent::OpenUrlJournalBookBaseSchema('openurl-1.0-book');

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