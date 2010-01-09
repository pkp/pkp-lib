<?php

/**
 * @file classes/metadata/OpenUrlBookSchema.inc.php
 *
 * Copyright (c) 2000-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OpenUrlBookSchema
 * @ingroup metadata
 * @see OpenUrlJournalBookBaseSchema
 *
 * @brief Class that provides meta-data properties of the
 *  OpenURL 1.0 book standard.
 */

// $Id$

import('metadata.OpenUrlJournalBookBaseSchema');

class OpenUrlBookSchema extends OpenUrlJournalBookBaseSchema {
	/**
	 * Constructor
	 */
	function OpenUrlBookSchema() {
		// Add meta-data properties that only appear in the OpenURL book standard
		$this->addProperty(new MetadataProperty('btitle'));
		$this->addProperty(new MetadataProperty('place')); // Place of publication
		$this->addProperty(new MetadataProperty('pub'));   // Publisher
		$this->addProperty(new MetadataProperty('edition'));
		$this->addProperty(new MetadataProperty('tpages'));
		$this->addProperty(new MetadataProperty('series')); // The title of a series in which the book or document was issued.
		$this->addProperty(new MetadataProperty('bici'));
	}
}
?>