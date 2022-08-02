<?php

/**
 * @file classes/metadata/MetadataDataObjectAdapter.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MetadataDataObjectAdapter
 * @ingroup metadata
 *
 * @see DataObject
 * @see MetadataSchema
 * @see MetadataDescription
 *
 * @brief Class that injects/extracts a meta-data description
 *  into/from an application entity object (DataObject).
 *
 *  These adapters have to be persistable as they'll be provided
 *  by plug-ins via the filter registry.
 */

namespace PKP\metadata;

use PKP\filter\PersistableFilter;

class MetadataDataObjectAdapter extends PersistableFilter
{
    public const METADATA_DOA_INJECTION_MODE = 1;
    public const METADATA_DOA_EXTRACTION_MODE = 2;

    /** @var int */
    public $_mode;

    /** @var MetadataSchema */
    public $_metadataSchema;

    /** @var string */
    public $_dataObjectClass;

    /** @var array */
    public $_metadataFieldNames;

    /** @var string */
    public $_metadataSchemaName;

    /** @var int */
    public $_assocType;

    /** @var string */
    public $_dataObjectName;

    /**
     * Constructor
     *
     * @param FilterGroup $filterGroup
     * @param null|mixed $mode
     */
    public function __construct($filterGroup, $mode = null)
    {
        // Initialize the adapter.
        parent::__construct($filterGroup);

        // Extract information from the input/output types.

        // Find out whether this filter is injecting or
        // extracting meta-data.
        $metadataTypeDescription = null; /** @var MetadataTypeDescription $metadataTypeDescription */
        $dataObjectTypeDescription = null; /** @var ClassTypeDescription $dataObjectTypeDescription */
        $inputType = & $this->getInputType();
        $outputType = & $this->getOutputType();
        if (is_null($mode)) {
            if ($inputType instanceof \PKP\metadata\MetadataTypeDescription) {
                $mode = self::METADATA_DOA_INJECTION_MODE;
            } else {
                $mode = self::METADATA_DOA_EXTRACTION_MODE;
            }
        }
        $this->_mode = $mode;

        if ($mode == self::METADATA_DOA_INJECTION_MODE) {
            // We are in meta-data injection mode (or both input and output are meta-data descriptions).
            $metadataTypeDescription = & $inputType; /** @var MetadataTypeDescription $metadataTypeDescription */
            assert($outputType instanceof \PKP\filter\ClassTypeDescription);
            $dataObjectTypeDescription = & $outputType; /** @var ClassTypeDescription $dataObjectTypeDescription */
        } else {
            // We are in meta-data extraction mode.
            assert($outputType instanceof \PKP\metadata\MetadataTypeDescription);
            $metadataTypeDescription = & $outputType;
            assert($inputType instanceof \PKP\filter\ClassTypeDescription);
            $dataObjectTypeDescription = & $inputType;
        }

        // Extract information from the input/output types.
        $this->_metadataSchemaName = $metadataTypeDescription->getMetadataSchemaClass();
        $this->_assocType = $metadataTypeDescription->getAssocType();
        $this->_dataObjectName = $dataObjectTypeDescription->getTypeName();

        // Set the display name.
        if ($mode == self::METADATA_DOA_INJECTION_MODE) {
            $this->setDisplayName('Inject metadata into a(n) ' . $this->getDataObjectClass());
        } else {
            $this->setDisplayName('Extract metadata from a(n) ' . $this->getDataObjectClass());
        }
    }

    //
    // Getters and setters
    //
    /**
     * One of the METADATA_DOA_*_MODE constants.
     *
     * @return int
     */
    public function getMode()
    {
        return $this->_mode;
    }

    /**
     * Get the fully qualified class name of
     * the supported meta-data schema.
     *
     * @return string
     */
    public function getMetadataSchemaName()
    {
        return $this->_metadataSchemaName;
    }

    /**
     * Get the supported meta-data schema (lazy load)
     *
     * @return MetadataSchema
     */
    public function &getMetadataSchema()
    {
        // Lazy-load the meta-data schema if this has
        // not been done before.
        if (is_null($this->_metadataSchema)) {
            $metadataSchemaName = $this->getMetadataSchemaName();
            assert(!is_null($metadataSchemaName));
            $this->_metadataSchema = & instantiate($metadataSchemaName, 'MetadataSchema');
            assert(is_object($this->_metadataSchema));
        }
        return $this->_metadataSchema;
    }

    /**
     * Convenience method that returns the
     * meta-data name space.
     *
     * @return string
     */
    public function getMetadataNamespace()
    {
        $metadataSchema = & $this->getMetadataSchema();
        return $metadataSchema->getNamespace();
    }

    /**
     * Get the supported application entity (class) name
     *
     * @return string
     */
    public function getDataObjectName()
    {
        return $this->_dataObjectName;
    }

    /**
     * Return the data object class name
     * (without the package prefix)
     *
     * @return string
     */
    public function getDataObjectClass()
    {
        if (is_null($this->_dataObjectClass)) {
            $dataObjectName = $this->getDataObjectName();
            assert(!is_null($dataObjectName));
            $dataObjectNameParts = explode('.', $dataObjectName);
            $this->_dataObjectClass = array_pop($dataObjectNameParts);
        }
        return $this->_dataObjectClass;
    }

    /**
     * Get the association type corresponding to the data
     * object type.
     *
     * @return int
     */
    public function getAssocType()
    {
        return $this->_assocType;
    }

    /**
     * Set the target data object for meta-data injection.
     *
     * @param DataObject $targetDataObject
     */
    public function setTargetDataObject(&$targetDataObject)
    {
        $this->_targetDataObject = & $targetDataObject;
    }

    /**
     * Get the target data object for meta-data injection.
     */
    public function &getTargetDataObject()
    {
        return $this->_targetDataObject;
    }


    //
    // Abstract template methods
    //
    /**
     * Inject a MetadataDescription into the target DataObject
     *
     * @param MetadataDescription $metadataDescription
     * @param DataObject $targetDataObject
     *
     * @return DataObject
     */
    public function &injectMetadataIntoDataObject(&$metadataDescription, &$targetDataObject)
    {
        // Must be implemented by sub-classes
        assert(false);
    }

    /**
     * Extract a MetadataDescription from a source DataObject.
     *
     * @param DataObject $sourceDataObject
     *
     * @return MetadataDescription
     */
    public function extractMetadataFromDataObject(&$sourceDataObject)
    {
        // Must be implemented by sub-classes
        assert(false);
    }

    /**
     * Return the additional field names introduced by the
     * meta-data schema that need to be persisted in the
     * ..._settings table corresponding to the DataObject
     * which is supported by this adapter.
     * NB: The field names must be prefixed with the meta-data
     * schema namespace identifier.
     *
     * @param bool $translated if true, return localized field
     *  names, otherwise return additional field names.
     *
     * @return array an array of field names to be persisted.
     */
    public function getDataObjectMetadataFieldNames($translated = true)
    {
        // By default return all field names
        return $this->getMetadataFieldNames($translated);
    }


    //
    // Implement template methods from Filter
    //
    /**
     * Convert a MetadataDescription to an application
     * object or vice versa.
     *
     * @see Filter::process()
     *
     * @param mixed $input either a MetadataDescription or an application object
     *
     * @return mixed either a MetadataDescription or an application object
     */
    public function &process(&$input)
    {
        // Do we inject or extract metadata?
        switch ($this->getMode()) {
            case self::METADATA_DOA_INJECTION_MODE:
                $targetDataObject = & $this->getTargetDataObject();

                // Instantiate a new data object if none was given.
                if (is_null($targetDataObject)) {
                    $targetDataObject = & $this->instantiateDataObject();
                    assert(is_a($targetDataObject, $this->getDataObjectName()));
                }

                // Inject meta-data into the data object.
                $output = & $this->injectMetadataIntoDataObject($input, $targetDataObject);
                break;

            case self::METADATA_DOA_EXTRACTION_MODE:
                $output = $this->extractMetadataFromDataObject($input);
                break;

            default:
                // Input should be validated by now.
                assert(false);
        }

        return $output;
    }


    //
    // Protected helper methods
    //
    /**
     * Instantiate a new data object of the
     * correct type.
     *
     * NB: This can be overridden by sub-classes for more complex
     * data objects. The standard implementation assumes there are
     * no constructor args to be set or configurations to be made.
     *
     * @return DataObject
     */
    public function &instantiateDataObject()
    {
        $dataObjectName = $this->getDataObjectName();
        assert(!is_null($dataObjectName));
        $dataObject = & instantiate($dataObjectName, $this->getDataObjectClass());
        return $dataObject;
    }

    /**
     * Instantiate a meta-data description that conforms to the
     * settings of this adapter.
     *
     * @return MetadataDescription
     */
    public function &instantiateMetadataDescription()
    {
        $metadataDescription = new MetadataDescription($this->getMetadataSchemaName(), $this->getAssocType());
        return $metadataDescription;
    }

    /**
     * Return all field names introduced by the
     * meta-data schema that might have to be persisted.
     *
     * @param bool $translated if true, return localized field
     *  names, otherwise return additional field names.
     *
     * @return array an array of field names to be persisted.
     */
    public function getMetadataFieldNames($translated = true)
    {
        // Do we need to build the field name cache first?
        if (is_null($this->_metadataFieldNames)) {
            // Initialize the cache array
            $this->_metadataFieldNames = [];

            // Retrieve all properties and add
            // their names to the cache
            $metadataSchema = & $this->getMetadataSchema();
            $metadataSchemaNamespace = $metadataSchema->getNamespace();
            $properties = & $metadataSchema->getProperties();
            foreach ($properties as $property) {
                $propertyAssocTypes = $property->getAssocTypes();
                if (in_array($this->_assocType, $propertyAssocTypes)) {
                    // Separate translated and non-translated property names
                    // and add the name space so that field names are unique
                    // across various meta-data schemas.
                    $this->_metadataFieldNames[$property->getTranslated()][] = $metadataSchemaNamespace . ':' . $property->getName();
                }
            }
        }

        // Return the field names
        return $this->_metadataFieldNames[$translated];
    }

    /**
     * Set several localized statements in a meta-data schema.
     *
     * @param MetadataDescription $metadataDescription
     * @param string $propertyName
     * @param array $localizedValues (keys: locale, values: localized values)
     */
    public function addLocalizedStatements(&$metadataDescription, $propertyName, $localizedValues)
    {
        if (is_array($localizedValues)) {
            foreach ($localizedValues as $locale => $values) {
                // Handle cardinality "many" and "one" in the same way.
                if (is_scalar($values)) {
                    $values = [$values];
                }
                foreach ($values as $value) {
                    $metadataDescription->addStatement($propertyName, $value, $locale);
                    unset($value);
                }
            }
        }
    }

    /**
     * Directly inject all fields that are not mapped to the
     * data object into the data object's data array for
     * automatic persistence by the meta-data framework.
     *
     * @param MetadataDescription $metadataDescription
     * @param DataObject $dataObject
     */
    public function injectUnmappedDataObjectMetadataFields(&$metadataDescription, &$dataObject)
    {
        // Handle translated and non-translated statements separately.
        foreach ([true, false] as $translated) {
            // Retrieve the unmapped fields.
            foreach ($this->getDataObjectMetadataFieldNames($translated) as $unmappedProperty) {
                // Identify the corresponding property name.
                [$namespace, $propertyName] = explode(':', $unmappedProperty);

                // Find out whether we have a statement for this unmapped property.
                if ($metadataDescription->hasStatement($propertyName)) {
                    // Add the unmapped statement directly to the
                    // data object.
                    if ($translated) {
                        $dataObject->setData($unmappedProperty, $metadataDescription->getStatementTranslations($propertyName));
                    } else {
                        $dataObject->setData($unmappedProperty, $metadataDescription->getStatement($propertyName));
                    }
                }
            }
        }
    }

    /**
     * Directly extract all fields that are not mapped to the
     * data object from the data object's data array.
     *
     * @param DataObject $dataObject
     * @param MetadataDescription $metadataDescription
     */
    public function extractUnmappedDataObjectMetadataFields(&$dataObject, &$metadataDescription)
    {
        $metadataSchema = & $this->getMetadataSchema();
        $handledNamespace = $metadataSchema->getNamespace();

        // Handle translated and non-translated statements separately.
        foreach ([true, false] as $translated) {
            // Retrieve the unmapped fields.
            foreach ($this->getDataObjectMetadataFieldNames($translated) as $unmappedProperty) {
                // Find out whether we have a statement for this unmapped property.
                if ($dataObject->hasData($unmappedProperty)) {
                    // Identify the corresponding property name and namespace.
                    [$namespace, $propertyName] = explode(':', $unmappedProperty);

                    // Only extract data if the namespace of the property
                    // is the same as the one handled by this adapter and the
                    // property is within the current description.
                    if ($namespace == $handledNamespace && $metadataSchema->hasProperty($propertyName)) {
                        // Add the unmapped statement to the metadata description.
                        if ($translated) {
                            $this->addLocalizedStatements($metadataDescription, $propertyName, $dataObject->getData($unmappedProperty));
                        } else {
                            $metadataDescription->addStatement($propertyName, $dataObject->getData($unmappedProperty));
                        }
                    }
                }
            }
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\metadata\MetadataDataObjectAdapter', '\MetadataDataObjectAdapter');
    define('METADATA_DOA_INJECTION_MODE', \MetadataDataObjectAdapter::METADATA_DOA_INJECTION_MODE);
    define('METADATA_DOA_EXTRACTION_MODE', \MetadataDataObjectAdapter::METADATA_DOA_EXTRACTION_MODE);
}
