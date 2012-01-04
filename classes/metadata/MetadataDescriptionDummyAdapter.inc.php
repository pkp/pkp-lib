<?php

/**
 * @file classes/metadata/MetadataDescriptionDummyAdapter.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MetadataDescriptionDummyAdapter
 * @ingroup metadata
 * @see MetadataDescription
 *
 * @brief Class that simulates a metadata adapter for metadata
 * description object for direct metadata description persistence.
 */

import('lib.pkp.classes.metadata.MetadataDataObjectAdapter');

class MetadataDescriptionDummyAdapter extends MetadataDataObjectAdapter {
	/**
	 * Constructor
	 *
	 * @param $metadataDescription MetadataDescription
	 */
	function MetadataDescriptionDummyAdapter($metadataDescription) {
		$this->setDisplayName('Inject/Extract Metadata into/from a MetadataDescription');

		// Configure the adapter
		parent::MetadataDataObjectAdapter($metadataDescription->getMetadataSchemaName(), 'lib.pkp.classes.metadata.MetadataDescription', $metadataDescription->getAssocType());
	}

	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.classes.metadata.MetadataDescriptionDummyAdapter';
	}


	//
	// Implement template methods from MetadataDataObjectAdapter
	//
	/**
	 * @see MetadataDataObjectAdapter::injectMetadataIntoDataObject()
	 * @param $metadataDescription MetadataDescription
	 * @param $dataObject MetadataDescription
	 * @param $replace boolean whether existing meta-data should be replaced
	 * @return DataObject
	 */
	function &injectMetadataIntoDataObject(&$metadataDescription, &$dataObject, $replace) {
		assert($metadataDescription->getMetadataSchema() == $dataObject->getMetadataSchema());
		$replace = ($replace ? METADATA_DESCRIPTION_REPLACE_ALL : METADATA_DESCRIPTION_REPLACE_NOTHING);
		$dataObject->setStatements($metadataDescription->getStatements(), $replace);
		return $dataObject;
	}

	/**
	 * @see MetadataDataObjectAdapter::extractMetadataFromDataObject()
	 * @param $dataObject MetadataDescription
	 * @return MetadataDescription
	 */
	function &extractMetadataFromDataObject(&$dataObject) {
		return $dataObject;
	}

	/**
	 * We override the standard implementation so that
	 * meta-data fields will be persisted without namespace
	 * prefix. This is ok as meta-data descriptions always
	 * only have meta-data from one namespace.
	 *
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
			$properties =& $metadataSchema->getProperties();
			foreach($properties as $property) {
				$propertyAssocTypes = $property->getAssocTypes();
				if (in_array($this->_assocType, $propertyAssocTypes)) {
					// Separate translated and non-translated property names
					// and add the name space so that field names are unique
					// across various meta-data schemas.
					$this->_metadataFieldNames[$property->getTranslated()][] = $property->getName();
				}
			}
		}

		// Return the field names
		return $this->_metadataFieldNames[$translated];
	}
}
?>
