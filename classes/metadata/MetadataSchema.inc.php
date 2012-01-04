<?php

/**
 * @defgroup metadata
 */

/**
 * @file classes/metadata/MetadataSchema.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MetadataSchema
 * @ingroup metadata
 * @see MetadataProperty
 * @see MetadataRecord
 *
 * @brief Class that represents a meta-data schema (e.g. NLM element-citation,
 *  OpenURL, dc(terms), etc.
 *
 *  NB: We currently provide meta-data schemas as classes for better performance
 *  and code readability. It might, however, be necessary to maintain meta-data
 *  schemas in the database for higher flexibility and easier run-time configuration/
 *  installation of new schemas.
 */

// $Id$


import('metadata.MetadataProperty');

class MetadataSchema {
	/** @var string */
	var $_name;

	/** @var string */
	var $_namespace;

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
	 * Get the internal namespace qualifier of the schema
	 * @return string
	 */
	function getNamespace() {
		return $this->_namespace;
	}

	/**
	 * Set the internal namespace qualifier of the schema
	 * @param $namespace string
	 */
	function setNamespace($namespace) {
		$this->_namespace = $namespace;
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
	 * Returns the property id with prefixed name space
	 * for use in an external context (e.g. Forms, Templates).
	 * @param $propertyName string
	 * @return string
	 */
	function getNamespacedPropertyId($propertyName) {
		$property =& $this->getProperty($propertyName);
		assert(is_a($property, 'MetadataProperty'));
		return $this->getNamespace().ucfirst($property->getId());
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
	 * Get the names of properties with a given data type.
	 * @param $propertyType mixed a valid property type description
	 * @return array an array of string values representing valid property names
	 */
	function getPropertyNamesByType($propertyType) {
		assert(in_array($propertyType, MetadataProperty::getSupportedTypes()));

		$propertyNames = array();
		foreach($this->_properties as $property) {
			if (in_array($propertyType, $property->getTypes())) {
				$propertyNames[] = $property->getName();
			}
		}

		return $propertyNames;
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