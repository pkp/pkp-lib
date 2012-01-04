<?php

/**
 * @file classes/metadata/OpenUrlDissertationSchema.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OpenUrlDissertationSchema
 * @ingroup metadata_openurl
 * @see OpenUrlBaseSchema
 *
 * @brief Class that provides meta-data properties of the
 *  OpenURL 1.0 dissertation standard.
 */

// $Id$

import('metadata.openurl.OpenUrlBaseSchema');

// "dissertation" is not defined as genre in the standard. We only use it internally.
define('OPENURL_PSEUDOGENRE_DISSERTATION', 'dissertation');

class OpenUrlDissertationSchema extends OpenUrlBaseSchema {
	/**
	 * Constructor
	 */
	function OpenUrlDissertationSchema() {
		$this->setName('openurl-1.0-dissertation');

		parent::OpenUrlBaseSchema();

		// Add meta-data properties that only appear in the OpenURL dissertation standard
		$citation = array(ASSOC_TYPE_CITATION);
		$this->addProperty(new MetadataProperty('co', $citation)); // Country of publication (plain text)
		$this->addProperty(new MetadataProperty('cc', $citation)); // Country of publication (ISO 2-character code)
		$this->addProperty(new MetadataProperty('inst', $citation)); // Institution that issued the dissertation
		$this->addProperty(new MetadataProperty('advisor', $citation));
		$this->addProperty(new MetadataProperty('tpages', $citation, METADATA_PROPERTY_TYPE_INTEGER));
		$this->addProperty(new MetadataProperty('degree', $citation));
	}
}
?>