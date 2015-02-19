<?php

/**
 * @file tests/classes/metadata/TestSchema.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class TestSchema
 * @ingroup tests_classes_metadata
 * @see MetadataSchema
 *
 * @brief Class that provides typical meta-data properties for
 *  testing purposes.
 */


import('lib.pkp.classes.metadata.MetadataSchema');

class TestSchema extends MetadataSchema {
	/**
	 * Constructor
	 */
	function TestSchema() {
		// Configure the meta-data schema.
		parent::MetadataSchema(
			'test-schema',
			'test',
			'lib.pkp.tests.classes.metadata.TestSchema',
			ASSOC_TYPE_CITATION
		);

		$this->addProperty('not-translated-one', METADATA_PROPERTY_TYPE_STRING, false, METADATA_PROPERTY_CARDINALITY_ONE);
		$this->addProperty('not-translated-many', METADATA_PROPERTY_TYPE_STRING, false, METADATA_PROPERTY_CARDINALITY_MANY);
		$this->addProperty('translated-one', METADATA_PROPERTY_TYPE_STRING, true, METADATA_PROPERTY_CARDINALITY_ONE);
		$this->addProperty('translated-many', METADATA_PROPERTY_TYPE_STRING, true, METADATA_PROPERTY_CARDINALITY_MANY);
		$this->addProperty('composite-translated-many', METADATA_PROPERTY_TYPE_STRING, true, METADATA_PROPERTY_CARDINALITY_MANY);
	}
}
?>
