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
 */

// $Id$

import('filter.Filter');

define('METADATA_DATAOBJECT_ADAPTER_MODE_UNDEFINED', 0x01);
define('METADATA_DATAOBJECT_ADAPTER_MODE_REC_TO_APP', 0x01);
define('METADATA_DATAOBJECT_ADAPTER_MODE_APP_TO_REC', 0x02);

class MetadataDataObjectAdapter extends Filter {
	/** @var MetadataSchema */
	var $_metadataSchema;

	/** @var string */
	var $_dataObjectName;

	/** @var integer */
	var $_assocType;

	/** @var integer */
	var $_currentAdapterMode = METADATA_DATAOBJECT_ADAPTER_MODE_UNDEFINED;

	/** @var array */
	var $_metadataFieldNames;

	/**
	 * Constructor
	 * @param $metadataSchema MetadataSchema
	 * @param $dataObjectName string
	 * @param $assocType integer
	 */
	function MetadataDataObjectAdapter(&$metadataSchema, $dataObjectName, $assocType) {
		assert(is_a($metadataSchema, 'MetadataSchema') && is_string($dataObjectName)
				&& is_integer($assocType));

		// Initialize the adapter
		$this->_metadataSchema =& $metadataSchema;
		$this->_dataObjectName = $dataObjectName;
		$this->_assocType = $assocType;
	}

	//
	// Getters and setters
	//
	/**
	 * Get the supported meta-data schema
	 * @return MetadataSchema
	 */
	function &getMetadataSchema() {
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
	 * Get the supported application entity (class) name
	 * @return string
	 */
	function getDataObjectName() {
		return $this->_dataObjectName;
	}

	/**
	 * Get the association type corresponding to the data
	 * object type.
	 * @return integer
	 */
	function getAssocType() {
		return $this->_assocType;
	}

	//
	// Abstract template methods
	//
	/**
	 * Inject a MetadataDescription into a DataObject
	 * @param $metadataDescription MetadataDescription
	 * @param $dataObject DataObject
	 * @return DataObject
	 */
	function &injectMetadataIntoDataObject(&$metadataDescription, &$dataObject) {
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
	 */
	function supports(&$input) {
		switch(true) {
			// Inject meta-data into an existing data object
			case is_array($input):
				// We expect two array entries: a MetadataDescription and a target data object.
				if (count($input) != 2) return false;
				$metadataDescription =& $input[0];
				if (!is_a($metadataDescription, 'MetadataDescription')) return false;

				$dataObject =& $input[1];
				if (!is_a($dataObject, $this->_dataObjectName)) return false;

				// Check the the meta-data description compliance
				return $this->_complies($metadataDescription);

			// Inject meta-data into a new data object
			case is_a($input, 'MetadataDescription'):
				// We just need to check the meta-data description compliance.
				return $this->_complies($input);

			// Create a new meta-data description from a data object
			case is_a($input, $this->_dataObjectName):
				return true;

			default:
				// A non-supported data-type
				return false;
		}
	}

	/**
	 * Convert a MetadataDescription to an application
	 * object or vice versa.
	 * @see Filter::process()
	 * @param $input mixed either a MetadataDescription or an application object
	 * @return mixed either a MetadataDescription or an application object
	 */
	function &process(&$input) {
		// Set the adapter mode and convert the input.
		switch (true) {
			case is_array($input):
				$this->_currentAdapterMode = METADATA_DATAOBJECT_ADAPTER_MODE_REC_TO_APP;
				$output =& $this->injectMetadataIntoDataObject($input[0], $input[1]);
				break;

			case is_a($input, 'MetadataDescription'):
				$this->_currentAdapterMode = METADATA_DATAOBJECT_ADAPTER_MODE_REC_TO_APP;
				$nullVar = null;
				$output =& $this->injectMetadataIntoDataObject($input, $nullVar);
				break;

			case is_a($input, $this->_dataObjectName):
				$this->_currentAdapterMode = METADATA_DATAOBJECT_ADAPTER_MODE_APP_TO_REC;
				$output =& $this->extractMetadataFromDataObject($input);
				break;

			default:
				// Input should be validated by now.
				assert(false);
		}

		return $output;
	}

	/**
	 * @see Filter::isValid()
	 * @param $output MetadataDescription
	 */
	function isValid(&$output) {
		switch($this->_currentAdapterMode) {
			case METADATA_DATAOBJECT_ADAPTER_MODE_REC_TO_APP:
				// We expect an application object (DataObject)
				return is_a($output, $this->_dataObjectName);

			case METADATA_DATAOBJECT_ADAPTER_MODE_APP_TO_REC:
				if (!is_a($output, 'MetadataDescription')) return false;

				// Check whether the the output
				// complies with the supported schema
				return $this->_complies($output);

			default:
				// The adapter mode must always be defined
				// when calling isValid().
				assert(false);
		}
	}


	//
	// Overridden methods
	//
	/**
	 * Override the base method to re-set the adapter mode.
	 * @see Filter::filter()
	 */
	function &filter(&$input) {
		$output =& parent::filter($input);

		// Re-set the adapter mode before leaving the filter.
		$this->_currentAdapterMode = METADATA_DATAOBJECT_ADAPTER_MODE_UNDEFINED;
		return $output;
	}

	//
	// Protected helper methods
	//
	/**
	 * Instantiate a meta-data description that conforms to the
	 * settings of this adapter.
	 * @return MetadataDescription
	 */
	function &instantiateMetadataDescription() {
		$metadataDescription = new MetadataDescription($this->getMetadataSchema(), $this->getAssocType());
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

	//
	// Private helper methods
	//
	/**
	 * Check whether a given meta-data description complies with
	 * the meta-data schema configured for this adapter.
	 * @param $metadataDescription MetadataDescription
	 * @return boolean true if the given description complies, otherwise false
	 */
	function _complies($metadataDescription) {
		// Check that the description describes the correct resource
		if ($metadataDescription->getAssocType() != $this->_assocType) return false;

		// Check that the description complies with the correct schema
		$descriptionSchema =& $metadataDescription->getMetadataSchema();
		$supportedSchema =& $this->_metadataSchema;
		if ($descriptionSchema->getName() != $supportedSchema->getName()) return false;

		// Compliance was successfully checked
		return true;
	}
}
?>