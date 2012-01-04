<?php

/**
 * @defgroup metadata_openurl
 */

/**
 * @file classes/metadata/OpenUrlBaseSchema.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OpenUrlBaseSchema
 * @ingroup metadata_openurl
 * @see MetadataSchema
 *
 * @brief Class that provides meta-data properties common to all
 *  variants of the OpenURL 1.0 standard.
 */

// $Id$

import('metadata.MetadataSchema');

class OpenUrlBaseSchema extends MetadataSchema {
	/**
	 * Constructor
	 */
	function OpenUrlBaseSchema() {
		$this->setNamespace('openurl10');

		// Add meta-data properties common to all OpenURL standards
		$citation = array(ASSOC_TYPE_CITATION);
		$this->addProperty(new MetadataProperty('aulast', $citation));
		$this->addProperty(new MetadataProperty('aufirst', $citation));
		$this->addProperty(new MetadataProperty('auinit', $citation));   // First author's first and middle initials
		$this->addProperty(new MetadataProperty('auinit1', $citation));  // First author's first initial
		$this->addProperty(new MetadataProperty('auinitm', $citation));  // First author's middle initial
		$this->addProperty(new MetadataProperty('ausuffix', $citation)); // e.g.: "Jr", "III", etc.
		$this->addProperty(new MetadataProperty('au', $citation, METADATA_PROPERTY_TYPE_STRING, false, METADATA_PROPERTY_CARDINALITY_MANY));
		$this->addProperty(new MetadataProperty('title', $citation));    // Deprecated in book/journal 1.0, prefer jtitle/btitle, ok for dissertation
		$this->addProperty(new MetadataProperty('date', $citation, METADATA_PROPERTY_TYPE_DATE)); // Publication date
		$this->addProperty(new MetadataProperty('isbn', $citation));
	}
}
?>