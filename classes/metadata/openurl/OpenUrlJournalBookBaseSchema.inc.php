<?php

/**
 * @file classes/metadata/openurl/OpenUrlJournalBookBaseSchema.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OpenUrlJournalBookBaseSchema
 * @ingroup metadata_openurl
 * @see OpenUrlBaseSchema
 *
 * @brief Class that provides meta-data properties common to the
 *  journal and book variants of the OpenURL 1.0 standard.
 */


import('lib.pkp.classes.metadata.openurl.OpenUrlBaseSchema');

define('OPENURL_GENRE_CONFERENCE', 'conference');
define('OPENURL_GENRE_PROCEEDING', 'proceeding');
define('OPENURL_GENRE_UNKNOWN', 'unknown');

class OpenUrlJournalBookBaseSchema extends OpenUrlBaseSchema {
	/**
	 * Constructor
	 * @param $name string the meta-data schema name
	 */
	function OpenUrlJournalBookBaseSchema($name) {
		parent::OpenUrlBaseSchema($name);

		// Add meta-data properties common to the OpenURL book/journal standard
		$this->addProperty('aucorp');   // Organization or corporation that is the author or creator
		$this->addProperty('atitle');
		$this->addProperty('spage', METADATA_PROPERTY_TYPE_INTEGER);
		$this->addProperty('epage', METADATA_PROPERTY_TYPE_INTEGER);
		$this->addProperty('pages');
		$this->addProperty('issn');
	}
}
?>