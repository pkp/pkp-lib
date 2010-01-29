<?php

/**
 * @file classes/metadata/OpenUrlDissertationSchema.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
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

class OpenUrlDissertationSchema extends OpenUrlBaseSchema {
	/**
	 * Constructor
	 */
	function OpenUrlDissertationSchema() {
		$this->setName('openurl-1.0-dissertation');

		// Add meta-data properties that only appear in the OpenURL dissertation standard
		$this->addProperty(new MetadataProperty('co')); // Country of publication (plain text)
		$this->addProperty(new MetadataProperty('cc')); // Country of publication (ISO 2-character code)
		$this->addProperty(new MetadataProperty('inst')); // Institution that issued the dissertation
		$this->addProperty(new MetadataProperty('advisor'));
		$this->addProperty(new MetadataProperty('tpages', array(), METADATA_PROPERTY_TYPE_INTEGER));
		$this->addProperty(new MetadataProperty('degree'));
	}
}
?>