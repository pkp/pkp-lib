<?php

/**
 * @file tests/classes/metadata/TestSchema.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class TestSchema
 * @ingroup tests_classes_metadata
 * @see MetadataSchema
 *
 * @brief Class that provides typical meta-data properties for
 *  testing purposes.
 */

// $Id$

import('lib.pkp.classes.metadata.MetadataSchema');

class TestSchema extends MetadataSchema {
	/**
	 * Constructor
	 */
	function TestSchema() {
		$this->setName('test-schema');

		$types = array(ASSOC_TYPE_CITATION);
		$this->addProperty(new MetadataProperty('not-translated-one', $types, METADATA_PROPERTY_TYPE_STRING, false, METADATA_PROPERTY_CARDINALITY_ONE));
		$this->addProperty(new MetadataProperty('not-translated-many', $types, METADATA_PROPERTY_TYPE_STRING, false, METADATA_PROPERTY_CARDINALITY_MANY));
		$this->addProperty(new MetadataProperty('translated-one', $types, METADATA_PROPERTY_TYPE_STRING, true, METADATA_PROPERTY_CARDINALITY_ONE));
		$this->addProperty(new MetadataProperty('translated-many', $types, METADATA_PROPERTY_TYPE_STRING, true, METADATA_PROPERTY_CARDINALITY_MANY));
		$this->addProperty(new MetadataProperty('composite-translated-many', $types, METADATA_PROPERTY_TYPE_STRING, true, METADATA_PROPERTY_CARDINALITY_MANY));
	}
}
?>