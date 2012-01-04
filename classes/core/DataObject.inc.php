<?php

/**
 * @file classes/core/DataObject.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class DataObject
 * @ingroup core
 * @see Core
 *
 * @brief Any class with an associated DAO should extend this class.
 */

// $Id$


class DataObject {
	/** Array of object data */
	var $_data;

	/** @var array an array of MetadataAdapter instances (one per supported schema) */
	var $_metadataAdapters = array();

	/**
	 * Constructor.
	 */
	function DataObject($callHooks = true) {
		// FIXME: Add meta-data schema plug-in support here to
		// dynamically add supported meta-data schemas.

		$this->_data = array();
	}

	//
	// Getters/Setters
	//
	function &getLocalizedData($key) {
		$localePrecedence = AppLocale::getLocalePrecedence();
		foreach ($localePrecedence as $locale) {
			$value =& $this->getData($key, $locale);
			if (!empty($value)) return $value;
			unset($value);
		}

		// Fallback: Get the first available piece of data.
		$data =& $this->getData($key, null);
		if (!empty($data)) return $data[array_shift(array_keys($data))];

		// No data available; return null.
		unset($data);
		$data = null;
		return $data;
	}

	/**
	 * Get the value of a data variable.
	 * @param $key string
	 * @param $locale string (optional)
	 * @return mixed
	 */
	function &getData($key, $locale = null) {
		if (is_null($locale)) {
			if (isset($this->_data[$key])) {
				return $this->_data[$key];
			}
		} else {
			// see http://bugs.php.net/bug.php?id=29848
			if (isset($this->_data[$key]) && is_array($this->_data[$key]) && isset($this->_data[$key][$locale])) {
				return $this->_data[$key][$locale];
			}
		}
		$nullVar = null;
		return $nullVar;
	}

	/**
	 * Set the value of a new or existing data variable.
	 * NB: Passing in null as a value will unset the
	 * data variable if it already existed.
	 * @param $key string
	 * @param $locale string (optional)
	 * @param $value mixed
	 */
	function setData($key, $value, $locale = null) {
		if (is_null($locale)) {
			if (is_null($value)) {
				if (isset($this->_data[$key])) unset($this->_data[$key]);
			} else {
				$this->_data[$key] = $value;
			}
		} else {
			if (is_null($value)) {
				// see http://bugs.php.net/bug.php?id=29848
				if (isset($this->_data[$key])) {
					if (is_array($this->_data[$key]) && isset($this->_data[$key][$locale])) unset($this->_data[$key][$locale]);
					// Was this the last entry for the data variable?
					if (empty($this->_data[$key])) unset($this->_data[$key]);
				}
			} else {
				$this->_data[$key][$locale] = $value;
			}
		}
	}

	/**
	 * Check whether a value exists for a given data variable.
	 * @param $key string
	 * @param $locale string (optional)
	 * @return boolean
	 */
	function hasData($key, $locale = null) {
		if (is_null($locale)) {
			return isset($this->_data[$key]);
		} else {
			// see http://bugs.php.net/bug.php?id=29848
			return isset($this->_data[$key]) && is_array($this->_data[$key]) && isset($this->_data[$key][$locale]);
		}
	}

	/**
	 * Return an array with all data variables.
	 * @return array
	 */
	function &getAllData() {
		return $this->_data;
	}

	/**
	 * Set all data variables at once.
	 * @param $data array
	 */
	function setAllData(&$data) {
		$this->_data =& $data;
	}

	/**
	 * Get ID of object.
	 * @return int
	 */
	function getId() {
		return $this->getData('id');
	}

	/**
	 * Set ID of object.
	 * @param $id int
	 */
	function setId($id) {
		return $this->setData('id', $id);
	}

	//
	// MetadataProvider interface implementation
	//
	/**
	 * Add a meta-data adapter that will be supported
	 * by this application entity. Only one adapter per schema
	 * can be added.
	 * @param $metadataAdapter MetadataAdapter
	 */
	function addSupportedMetadataAdapter(&$metadataAdapter) {
		$metadataSchema =& $metadataAdapter->getMetadataSchema();
		$metadataSchemaName = $metadataSchema->getName();

		// Make sure that the meta-data schema is unique.
		assert(!empty($metadataSchemaName) &&
				!isset($this->_metadataAdapters[$metadataSchemaName]));

		// Make sure that the adapter converts from/to this application entity
		assert($metadataAdapter->supportsAsInput($this));

		// Save adapter and schema
		$this->_metadataAdapters[$metadataSchemaName] =& $metadataAdapter;
	}

	/**
	 * Returns all supported meta-data adapters
	 * @return array
	 */
	function &getSupportedMetadataAdapters() {
		return $this->_metadataAdapters;
	}

	/**
	 * Convenience method that returns an array
	 * with all meta-data schemas that have corresponding
	 * meta-data adapters.
	 */
	function &getSupportedMetadataSchemas() {
		$supportedMetadataSchemas = array();
		foreach($this->getSupportedMetadataAdapters() as $metadataAdapter) {
			$supportedMetadataSchemas[] = $metadataAdapter->getMetadataSchema();
		}
		return $supportedMetadataSchemas;
	}

	/**
	 * Retrieve the names of meta-data
	 * properties of this data object.
	 * @param $translated boolean if true, return localized field
	 *  names, otherwise return additional field names.
	 */
	function getMetadataFieldNames($translated = true) {
		// Create a list of all possible meta-data field names
		$metadataFieldNames = array();
		foreach($this->_metadataAdapters as $metadataSchemaName => $metadataAdapter) {
			// Add the field names from the current adapter
			$metadataFieldNames = array_merge($metadataFieldNames,
					$metadataAdapter->getDataObjectMetadataFieldNames($translated));
		}
		$metadataFieldNames = array_unique($metadataFieldNames);
		return $metadataFieldNames;
	}

	/**
	 * Retrieve the names of meta-data
	 * properties that need to be persisted
	 * (i.e. that have data).
	 * @param $translated boolean if true, return localized field
	 *  names, otherwise return additional field names.
	 * @return array an array of field names
	 */
	function getSetMetadataFieldNames($translated = true) {
		// Retrieve a list of all possible meta-data field names
		$metadataFieldNameCandidates = $this->getMetadataFieldNames($translated);

		// Only retain those fields that have data
		$metadataFieldNames = array();
		foreach($metadataFieldNameCandidates as $metadataFieldNameCandidate) {
			if($this->hasData($metadataFieldNameCandidate)) {
				$metadataFieldNames[] = $metadataFieldNameCandidate;
			}
		}
		return $metadataFieldNames;
	}

	/**
	 * Retrieve the names of translated meta-data
	 * properties that need to be persisted.
	 * @return array an array of field names
	 */
	function getLocaleMetadataFieldNames() {
		return $this->getMetadataFieldNames(true);
	}

	/**
	 * Retrieve the names of additional meta-data
	 * properties that need to be persisted.
	 * @return array an array of field names
	 */
	function getAdditionalMetadataFieldNames() {
		return $this->getMetadataFieldNames(false);
	}

	/**
	 * Inject a meta-data description into this
	 * data object.
	 * @param $metadataDescription MetadataDescription
	 * @param $replace boolean whether to delete existing meta-data
	 * @return boolean true on success, otherwise false
	 */
	function injectMetadata(&$metadataDescription, $replace = false) {
		$dataObject = null;
		foreach($this->_metadataAdapters as $metadataAdapter) {
			// The first adapter that supports the given description
			// will be used to inject the meta-data into this data object.
			if ($metadataAdapter->supportsAsInput($metadataDescription)) {
				// Use adapter filter to convert from a meta-data
				// description to a data object.
				// NB: we pass in a reference to the data object which
				// the filter will use to update the current instance
				// of the data object.
				$input = array(&$metadataDescription, &$this, $replace);
				$dataObject =& $metadataAdapter->execute($input);
				break;
			}
		}
		return $dataObject;
	}

	/**
	 * Inject a meta-data description into this
	 * data object.
	 * @param $metadataSchema MetadataSchema
	 * @return $metadataDescription MetadataDescription
	 */
	function &extractMetadata(&$metadataSchema) {
		$metadataDescription = null;
		foreach($this->_metadataAdapters as $metadataAdapter) {
			// The first adapter that supports the given meta-data schema
			// will be used to extract meta-data from this data object.
			$supportedMetadataSchema =& $metadataAdapter->getMetadataSchema();
			if ($metadataSchema->getName() == $supportedMetadataSchema->getName()) {
				// Use adapter filter to convert from a data object
				// to a meta-data description.
				$metadataDescription =& $metadataAdapter->execute($this);
				break;
			}
		}
		return $metadataDescription;
	}
}
?>
