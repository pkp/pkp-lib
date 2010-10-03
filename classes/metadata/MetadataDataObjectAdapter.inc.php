<?php

/**
 * @file classes/metadata/MetadataDataObjectAdapter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MetadataDataObjectAdapter
 * @ingroup metadata
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

import('lib.pkp.classes.filter.PersistentFilter');
import('lib.pkp.classes.metadata.MetadataDescription');

class MetadataDataObjectAdapter extends PersistableFilter {
	/** @var MetadataSchema */
	var $_metadataSchema;

	/** @var string */
	var $_dataObjectClass;

	/** @var array */
	var $_metadataFieldNames;

	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function MetadataDataObjectAdapter(&$filterGroup) {
		assert(is_string($metadataSchemaName) && is_string($dataObjectName)
				&& is_integer($assocType));

		// Configure the persistable settings.
		$this->addSetting(new FilterSetting('metadataSchemaName', null, null));
		$this->addSetting(new FilterSetting('dataObjectName', null, null));
		$this->addSetting(new FilterSetting('assocType', null, null));

		// Initialize the adapter.
		$this->setDisplayName('Inject/Extract Metadata into/from a '.$dataObjectName);
		parent::PersistableFilter($filterGroup);
	}

	//
	// Getters and setters
	//
	/**
	 * Set the fully qualified class name of
	 * the supported meta-data schema.
	 * @param string
	 */
	function setMetadataSchemaName($metadataSchemaName) {
		return $this->setData('metadataSchemaName', $metadataSchemaName);
	}

	/**
	 * Get the fully qualified class name of
	 * the supported meta-data schema.
	 * @return string
	 */
	function getMetadataSchemaName() {
		return $this->getData('metadataSchemaName');
	}

	/**
	 * Get the supported meta-data schema (lazy load)
	 * @return MetadataSchema
	 */
	function &getMetadataSchema() {
		// Lazy-load the meta-data schema if this has
		// not been done before.
		if (is_null($this->_metadataSchema)) {
			$metadataSchemaName = $this->getMetadataSchemaName();
			assert(!is_null($metadataSchemaName));
			$this->_metadataSchema =& instantiate($metadataSchemaName, 'MetadataSchema');
			assert(is_object($this->_metadataSchema));
		}
		return $this->_metadataSchema;
	}

	/**
	 * Convenience method that returns the
	 * meta-data name space.
	 * @return string
	 */
	function getMetadataNamespace() {
		$metadataSchema =& $this->getMetadataSchema();
		return $metadataSchema->getNamespace();
	}

	/**
	 * set the supported application entity (class) name
	 * @param string
	 */
	function setDataObjectName($dataObjectName) {
		$this->setData('dataObjectName', $dataObjectName);
	}

	/**
	 * Get the supported application entity (class) name
	 * @return string
	 */
	function getDataObjectName() {
		return $this->getData('dataObjectName');
	}

	/**
	 * Return the data object class name
	 * (without the package prefix)
	 *
	 * @return string
	 */
	function getDataObjectClass() {
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
	 * @param integer
	 */
	function getAssocType($assocType) {
		$this->setData('assocType', $assocType);
	}

	/**
	 * Get the association type corresponding to the data
	 * object type.
	 * @return integer
	 */
	function getAssocType() {
		return $this->getData('assocType');
	}

	/**
	 * @param $dataObject DataObject
	 */

	/**
	 * @param $replace boolean whether to delete existing meta-data
	 */


	//
	// Abstract template methods
	//
	/**
	 * Inject a MetadataDescription into a DataObject
	 * @param $metadataDescription MetadataDescription
	 * @param $dataObject DataObject
	 * @param $replace boolean whether to delete existing meta-data
	 * @return DataObject
	 */
	function &injectMetadataIntoDataObject(&$metadataDescription, &$dataObject, $replace) {
		// Must be implemented by sub-classes
		assert(false);
	}

	/**
	 * Extract a MetadataDescription from a DataObject.
	 * @param $dataObject DataObject
	 * @return MetadataDescription
	 */
	function &extractMetadataFromDataObject(&$dataObject) {
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
	 * @param $translated boolean if true, return localized field
	 *  names, otherwise return additional field names.
	 * @return array an array of field names to be persisted.
	 */
	function getDataObjectMetadataFieldNames($translated = true) {
		// By default return all field names
		return $this->getMetadataFieldNames($translated);
	}


	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::supports()
	 * @param $input mixed
	 * @param $output mixed
	 * @return boolean
	 */
	function supports(&$input, &$output) {
		// Intercept Filter::supports() to check the consistency of
		// the filter parameterization with the filter group.

		// Get the input/output types.
		$inputType =& $this->getInputType();
		$outputType =& $this->getOutputType();

		// Get the input/output type descriptions.
		$inputTypeDescription = $inputType->getTypeDescription();
		$outputTypeDescription = $outputType->getTypeDescription();

		// Get the supported transformations.
		$supportedTransformations = $this->_getSupportedTransformations();

		// Check whether the input/output type descriptions
		// match the supported transformations.
		foreach($supportedTransformations as $supportedTransformation) {
			list($supportedInputType, $supportedOutputType) = $supportedTransformation;

			// Go on only if the configured input/output type match the
			// supported types.
			if ($inputTypeDescription == $supportedInputType &&
					$outputTypeDescription == $supportedOutputType) {
				// Let the filter framework do the rest of the checking.
				return parent::supports($input, $output);
			}
		}

		// None of the supported transformations matched. This is a
		// fatal configuration or coding error.
		fatalError('Found inconsistent configuration of input/output types in \''.get_class($this).'\'!');
	}

	/**
	 * Convert a MetadataDescription to an application
	 * object or vice versa.
	 * @see Filter::process()
	 * @param $input mixed either a MetadataDescription or an application object
	 * @return mixed either a MetadataDescription or an application object
	 */
	function &process(&$input) {
		// Do we inject or extract metadata?
		switch (true) {
			case is_a($input, 'MetadataDescription'):
				$dataObject =& $this->getDataObject();

				// Instantiate a new data object if none was given.
				if (is_null($dataObject)) {
					$dataObject =& $this->instantiateDataObject();
					assert(is_a($dataObject, $this->getDataObjectName()));
				}

				// Inject meta-data into the data object.
				$output =& $this->injectMetadataIntoDataObject($input, $dataObject);
				break;

			case is_a($input, $this->getDataObjectClass()):
				$output =& $this->extractMetadataFromDataObject($input);
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
	function &instantiateDataObject() {
		$dataObjectName = $this->getDataObjectName();
		assert(!is_null($dataObjectName));
		$dataObject =& instantiate($dataObjectName, $this->getDataObjectClass());
		return $dataObject;
	}

	/**
	 * Instantiate a meta-data description that conforms to the
	 * settings of this adapter.
	 * @return MetadataDescription
	 */
	function &instantiateMetadataDescription() {
		$metadataDescription = new MetadataDescription($this->getMetadataSchemaName(), $this->getAssocType());
		return $metadataDescription;
	}

	/**
	 * Return all field names introduced by the
	 * meta-data schema that might have to be persisted.
	 * @param $translated boolean if true, return localized field
	 *  names, otherwise return additional field names.
	 * @return array an array of field names to be persisted.
	 */
	function getMetadataFieldNames($translated = true) {
		// Do we need to build the field name cache first?
		if (is_null($this->_metadataFieldNames)) {
			// Initialize the cache array
			$this->_metadataFieldNames = array();

			// Retrieve all properties and add
			// their names to the cache
			$metadataSchema =& $this->getMetadataSchema();
			$metadataSchemaNamespace = $metadataSchema->getNamespace();
			$properties =& $metadataSchema->getProperties();
			foreach($properties as $property) {
				$propertyAssocTypes = $property->getAssocTypes();
				if (in_array($this->_assocType, $propertyAssocTypes)) {
					// Separate translated and non-translated property names
					// and add the name space so that field names are unique
					// across various meta-data schemas.
					$this->_metadataFieldNames[$property->getTranslated()][] = $metadataSchemaNamespace.':'.$property->getName();
				}
			}
		}

		// Return the field names
		return $this->_metadataFieldNames[$translated];
	}

	/**
	 * Set several localized statements in a meta-data schema.
	 * @param $metadataDescription MetadataDescription
	 * @param $propertyName string
	 * @param $localizedValues array (keys: locale, values: localized values)
	 */
	function addLocalizedStatements(&$metadataDescription, $propertyName, $localizedValues) {
		if (is_array($localizedValues)) {
			foreach ($localizedValues as $locale => $values) {
				// Handle cardinality "many" and "one" in the same way.
				if (is_scalar($values)) $values = array($values);
				foreach($values as $value) {
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
	 * @param $metadataDescription MetadataDescription
	 * @param $dataObject DataObject
	 */
	function injectUnmappedDataObjectMetadataFields(&$metadataDescription, &$dataObject) {
		// Handle translated and non-translated statements separately.
		foreach(array(true, false) as $translated) {
			// Retrieve the unmapped fields.
			foreach($this->getDataObjectMetadataFieldNames($translated) as $unmappedProperty) {
				// Identify the corresponding property name.
				list($namespace, $propertyName) = explode(':', $unmappedProperty);

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
	 * @param $dataObject DataObject
	 * @param $metadataDescription MetadataDescription
	 */
	function extractUnmappedDataObjectMetadataFields(&$dataObject, &$metadataDescription) {
		$metadataSchema =& $this->getMetadataSchema();
		$handledNamespace = $metadataSchema->getNamespace();

		// Handle translated and non-translated statements separately.
		foreach(array(true, false) as $translated) {
			// Retrieve the unmapped fields.
			foreach($this->getDataObjectMetadataFieldNames($translated) as $unmappedProperty) {
				// Find out whether we have a statement for this unmapped property.
				if ($dataObject->hasData($unmappedProperty)) {
					// Identify the corresponding property name and namespace.
					list($namespace, $propertyName) = explode(':', $unmappedProperty);

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


	//
	// Private helper methods
	//
	/**
	 * Construct the supported input/output types from the
	 * parameterization of the filter.
	 * @see MetadataObjectAdapter::supports()
	 * @return array a list of supported transformations as a two-dimensional array.
	 */
	function _getSupportedTransformations() {
		// Retrieve filter parameters.
		$metadataSchemaName = $this->getMetadataSchemaName();
		$dataObjectName = $this->getDataObjectName();
		$assocType = $this->getAssocType();

		// Make sure that all parameters are set.
		if (is_null($metadataSchemaName) || is_null($dataObjectName) || is_null($assocType)) return false;

		// Find the ASSOC_TYPE_* constant with the correct value.
		$definedConstants = array_keys(get_defined_constants());
		$assocTypeConstants = array_filter($definedConstants,
				create_function('$o', 'return (strpos($o, "ASSOC_TYPE_") === 0) && '
				.'(constant($o) === '.(string)$this->getAssocType().');'));
		assert(count($assocTypeConstants) == 1);

		// Extract the assoc type name.
		$assocTypeName = str_replace('ASSOC_TYPE_', '', array_pop($assocTypeConstants));

		// Construct the supported type definitions.
		$metadataType = 'metadata::'.$this->getMetadataSchemaName().'('.$assocTypeName.')';
		$dataObjectType = 'class::'.$this->getDataObjectName();

		// Construct and return the supported transformations.
		return array(
			array($metadataType, $dataObjectType),
			array($dataObjectType, $metadataType)
		);
	}
}
?>