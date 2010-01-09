<?php

/**
 * @file classes/metadata/MetadataSchema.inc.php
 *
 * Copyright (c) 2000-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MetadataSchema
 * @ingroup metadata
 * @see MetadataProperty
 * @see MetadataRecord
 *
 * @brief Class that represents a meta-data schema (e.g. NLM element-citation,
 *  OpenURL, dc(terms), etc.
 */

// $Id$


import('metadata.MetadataProperty');

class MetadataSchema {
	/** @var string */
	var $_name;

	/**
	 * @var array meta-data properties (predicates)
	 *  supported for this meta-data schema.
	 */
	var $_properties = array();

	//
	// Get/set methods
	//
	/**
	 * Get the name of the schema
	 * @return string
	 */
	function getName() {
		return $this->_name;
	}

	/**
	 * Set the name of the schema
	 * @param $name string
	 */
	function setName($name) {
		$this->_name = $name;
	}


	/**
	 * Get the properties of the meta-data schema.
	 * @return array an array of MetadataProperties
	 */
	function &getProperties() {
		return $this->_properties;
	}

	/**
	 * Get a property. Returns null if the property
	 * doesn't exist.
	 * @return MetadataProperty
	 */
	function &getProperty($propertyName) {
		assert(is_string($propertyName));
		if ($this->hasProperty($propertyName)) {
			$property =& $this->_properties[$propertyName];
		} else {
			$property = null;
		}
		return $property;
	}

	/**
	 * (Re-)set all properties of this meta-data schema.
	 * @param $properties array an array of MetadataProperties
	 */
	function setProperties(&$properties) {
		// Remove the existing properties
		$this->_properties = array();

		// Insert the new properties
		foreach($properties as $property) {
			$this->addProperty($property);
		}
	}

	/**
	 * Add a property to this meta-data schema
	 * @param $element MetadataElement
	 */
	function addProperty(&$property) {
		assert(is_a($property, 'MetadataProperty'));
		$propertyName = $property->getName();

		// Make sure that this property has not been added before
		assert(!is_null($propertyName) && !isset($this->_properties[$propertyName]));

		// Add the property
		$this->_properties[$propertyName] =& $property;
	}

	/**
	 * Get the property names defined for this meta-data schema
	 * @return array an array of string values representing valid property names
	 */
	function getPropertyNames() {
		return array_keys($this->_properties);
	}

	/**
	 * Checks whether a property exists in the meta-data schema
	 * @param $propertyName string
	 * @return boolean
	 */
	function hasProperty($propertyName) {
		return isset($this->_properties[$propertyName]);
	}
}
?>