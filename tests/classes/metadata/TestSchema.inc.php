<?php

/**
 * @file tests/classes/metadata/TestSchema.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TestSchema
 * @ingroup tests_classes_metadata
 *
 * @see MetadataSchema
 *
 * @brief Class that provides typical meta-data properties for
 *  testing purposes.
 */

use PKP\metadata\MetadataProperty;
use PKP\metadata\MetadataSchema;

class TestSchema extends MetadataSchema
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Configure the meta-data schema.
        parent::__construct(
            'test-schema',
            'test',
            'lib.pkp.tests.classes.metadata.TestSchema',
            ASSOC_TYPE_CITATION
        );

        $this->addProperty('not-translated-one', MetadataProperty::METADATA_PROPERTY_TYPE_STRING, false, MetadataProperty::METADATA_PROPERTY_CARDINALITY_ONE);
        $this->addProperty('not-translated-many', MetadataProperty::METADATA_PROPERTY_TYPE_STRING, false, MetadataProperty::METADATA_PROPERTY_CARDINALITY_MANY);
        $this->addProperty('translated-one', MetadataProperty::METADATA_PROPERTY_TYPE_STRING, true, MetadataProperty::METADATA_PROPERTY_CARDINALITY_ONE);
        $this->addProperty('translated-many', MetadataProperty::METADATA_PROPERTY_TYPE_STRING, true, MetadataProperty::METADATA_PROPERTY_CARDINALITY_MANY);
        $this->addProperty('composite-translated-many', MetadataProperty::METADATA_PROPERTY_TYPE_STRING, true, MetadataProperty::METADATA_PROPERTY_CARDINALITY_MANY);
    }
}
