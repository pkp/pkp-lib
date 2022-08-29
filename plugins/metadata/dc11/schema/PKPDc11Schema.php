<?php
/**
 * @defgroup plugins_metadata_dc11_schema Dublin Core 1.1 Metadata Format Schema
 */

/**
 * @file plugins/metadata/dc11/schema/PKPDc11Schema.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPDc11Schema
 * @ingroup plugins_metadata_dc11_schema
 *
 * @see MetadataSchema
 *
 * @brief Class that provides meta-data properties compliant with
 *  the Dublin Core specification, version 1.1.
 *
 *  For details see <http://dublincore.org/documents/dces/>,
 */

namespace PKP\plugins\metadata\dc11\schema;

use PKP\metadata\MetadataProperty;
use PKP\metadata\MetadataSchema;

class PKPDc11Schema extends MetadataSchema
{
    /**
     * Constructor
     *
     * @param int $appSpecificAssocType
     */
    public function __construct($appSpecificAssocType, $classname = 'APP\plugins\metadata\dc11\schema\Dc11Schema')
    {
        // Configure the meta-data schema.
        parent::__construct(
            'dc-1.1',
            'dc',
            $classname,
            $appSpecificAssocType
        );

        $this->addProperty('dc:title', MetadataProperty::METADATA_PROPERTY_TYPE_STRING, true, MetadataProperty::METADATA_PROPERTY_CARDINALITY_MANY);
        $this->addProperty('dc:creator', MetadataProperty::METADATA_PROPERTY_TYPE_STRING, false, MetadataProperty::METADATA_PROPERTY_CARDINALITY_MANY);
        $this->addProperty('dc:subject', MetadataProperty::METADATA_PROPERTY_TYPE_STRING, true, MetadataProperty::METADATA_PROPERTY_CARDINALITY_MANY);
        $this->addProperty('dc:description', MetadataProperty::METADATA_PROPERTY_TYPE_STRING, true, MetadataProperty::METADATA_PROPERTY_CARDINALITY_MANY);
        $this->addProperty('dc:publisher', MetadataProperty::METADATA_PROPERTY_TYPE_STRING, true, MetadataProperty::METADATA_PROPERTY_CARDINALITY_MANY);
        $this->addProperty('dc:contributor', MetadataProperty::METADATA_PROPERTY_TYPE_STRING, true, MetadataProperty::METADATA_PROPERTY_CARDINALITY_MANY);
        $this->addProperty('dc:date', MetadataProperty::METADATA_PROPERTY_TYPE_STRING, false, MetadataProperty::METADATA_PROPERTY_CARDINALITY_MANY);
        $this->addProperty('dc:type', MetadataProperty::METADATA_PROPERTY_TYPE_STRING, true, MetadataProperty::METADATA_PROPERTY_CARDINALITY_MANY);
        $this->addProperty('dc:format', MetadataProperty::METADATA_PROPERTY_TYPE_STRING, false, MetadataProperty::METADATA_PROPERTY_CARDINALITY_MANY);
        $this->addProperty('dc:identifier', MetadataProperty::METADATA_PROPERTY_TYPE_STRING, false, MetadataProperty::METADATA_PROPERTY_CARDINALITY_MANY);
        $this->addProperty('dc:source', MetadataProperty::METADATA_PROPERTY_TYPE_STRING, true, MetadataProperty::METADATA_PROPERTY_CARDINALITY_MANY);
        $this->addProperty('dc:language', MetadataProperty::METADATA_PROPERTY_TYPE_STRING, false, MetadataProperty::METADATA_PROPERTY_CARDINALITY_MANY);
        $this->addProperty('dc:relation', MetadataProperty::METADATA_PROPERTY_TYPE_STRING, false, MetadataProperty::METADATA_PROPERTY_CARDINALITY_MANY);
        $this->addProperty('dc:coverage', MetadataProperty::METADATA_PROPERTY_TYPE_STRING, true, MetadataProperty::METADATA_PROPERTY_CARDINALITY_MANY);
        $this->addProperty('dc:rights', MetadataProperty::METADATA_PROPERTY_TYPE_STRING, true, MetadataProperty::METADATA_PROPERTY_CARDINALITY_MANY);
    }
}
