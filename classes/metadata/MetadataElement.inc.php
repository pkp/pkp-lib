<?php

/**
 * @file classes/metadata/MetadataElement.inc.php
 *
 * Copyright (c) 2000-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MetadataElement
 * @ingroup metadata
 * @see Metadata
 *
 * @brief Class representing metadata elements. It specifies type and cardinality
 *        of a meta-data element (=term, field, ...) and whether the element must
 *        be internationalized. It also provides a validator to test whether input
 *        conforms to the element specification. 
 */

// $Id$

// meta-data data types
define('METADATA_ELEMENT_TYPE_STRING', 0x01);
define('METADATA_ELEMENT_TYPE_DATE', 0x02);
define('METADATA_ELEMENT_TYPE_INTEGER', 0x03);
define('METADATA_ELEMENT_TYPE_OBJECT', 0x04);

// possible cardinalities of a meta-data element within a meta-data object
define('METADATA_ELEMENT_CARDINALITY_ONE', 0x01);
define('METADATA_ELEMENT_CARDINALITY_MANY', 0x02);

class MetadataElement extends DataObject {
	/**
	 * Constructor
	 * @param $name string the unique name of the element within a meta-data scheme
	 * @param $type integer must be one of the supported types, default: METADATA_ELEMENT_TYPE_STRING
	 * @param $translated boolean whether the element may have various language versions, default: false
	 * @param $cardinality integer must be on of the supported cardinalities, default: METADATA_ELEMENT_CARDINALITY_ONE
	 * @param $objectType if type is METADATA_ELEMENT_TYPE_OBJECT then this, default: null
	 *  gives the required object type (class name), otherwise null
	 */
	function MetadataElement($name, $type = METADATA_ELEMENT_TYPE_STRING,
			$translated = false, $cardinality = METADATA_ELEMENT_CARDINALITY_ONE,
			$objectType = null) {
				
		// make sure that we got an object type in case the element type is "object"
		if ($type == METADATA_ELEMENT_TYPE_OBJECT) {
			assert(isset($objectType));
		}

		// initialize the class
		$this->setName($name);
		$this->setType($type);
		$this->setTranslated($translated);
		$this->setCardinality($cardinality);
		$this->setObjectType($objectType);
	}

	/**
	 * get the name
	 * @return string
	 */
	function getName() {
		return $this->getData('name');
	}
	
	/**
	 * set the name
	 * @param $name string
	 */
	function setName($name) {
		$this->setData('name', (string)$name);
	}
	
	/**
	 * get the type
	 * @return integer
	 */
	function getType() {
		return $this->getData('type');
	}
	
	/**
	 * set the type
	 * @param $type integer
	 */
	function setType($type) {
		assert(is_null($type) || in_array($type, MetadataElement::_getSupportedTypes()));
		if (isset($type) && $type != METADATA_ELEMENT_TYPE_OBJECT) {
			// make sure that no object type has been set
			assert(is_null($this->getObjectType()));
		}
		$this->setData('type', (is_null($type) ? null : (integer)$type));
	}
	
	/**
	 * get the object type
	 * @return string
	 */
	function getObjectType() {
		return $this->getData('objectType');
	}
	
	/**
	 * set the object type
	 * @param $objectType string
	 */
	function setObjectType($objectType) {
		// make sure that we have either no type set or the element type is "object"
		$type = $this->getType();
		assert(is_null($objectType) || is_null($type) || $type == METADATA_ELEMENT_TYPE_OBJECT);
		$this->setData('objectType', $objectType);
	}
	
	/**
	 * is this element translated
	 * @return boolean
	 */
	function getTranslated() {
		return $this->getData('translated');
	}
	
	/**
	 * set whether this element is translated
	 * @param $translated boolean
	 */
	function setTranslated($translated) {
		$this->setData('translated', (boolean)$translated);
	}

	/**
	 * get the cardinality
	 * @return integer
	 */
	function getCardinality() {
		return $this->getData('cardinality');
	}
	
	/**
	 * set the cardinality
	 * @param $cardinality integer
	 */
	function setCardinality($cardinality) {
		assert(in_array($cardinality, MetadataElement::_getSupportedCardinalities()));
		$this->setData('cardinality', (integer)$cardinality);
	}
	
	/**
	 * validate a given input against the element specification
	 * 
	 * NB: We could invent some "MetaDataElementValidator" classes to
	 * modularize type- or cardinality-specific validation. But we don't
	 * as long as we have just a few types and simple validation
	 * algorithms.
	 * 
	 * @param $input mixed the input to be validated
	 * @return boolean validation success
	 */
	function validate($input) {
		// We never accept null values
		if (is_null($input)) return false;
		
		// validate cardinality
		switch ($this->getCardinality()) {
			case METADATA_ELEMENT_CARDINALITY_ONE:
				if (!is_scalar($input) && !is_object($input)) return false;
				
				// Transform the input to an array so that we can go on
				// with validation in a uniform way.
				$input = array($input);
				break;
			
			case METADATA_ELEMENT_CARDINALITY_MANY:
				if (!is_array($input)) return false;
				break;
			
			default:
				// As we validate cardinality in the setter, this should be unreachable code
				assert(false);
		}
		
		// run through all input elements
		foreach($input as $element) {
			// validate type
			switch ($this->getType()) {
				case METADATA_ELEMENT_TYPE_STRING:
					if (!is_string($element)) return false;
					break;
				
				case METADATA_ELEMENT_TYPE_DATE:
					// We allow the following patterns:
					// YYYY-MM-DD, YYYY-MM and YYYY
					$datePattern = '/^[0-9]{4}(-[0-9]{2}(-[0-9]{2})?)?$/';
					if (!preg_match($datePattern, $element)) return false;
					
					// Check whether the given string is really a valid date
					$dateParts = explode('-', $element);
					// Set the day and/or month to 1 if not set
					$dateParts = array_pad($dateParts, 3, 1);
					// Extract the date parts
					list($year, $month, $day) = $dateParts;
					// Validate the date (only leap days will pass unnoticed ;-) )
					// Who invented this argument order?
					if (!checkdate($month, $day, $year)) return false;
					break;
				
				case METADATA_ELEMENT_TYPE_INTEGER:
					if (!is_integer($element)) return false;
					break;
				
				case METADATA_ELEMENT_TYPE_OBJECT:
					// test the object type
					$objectType = $this->getObjectType();
					assert(isset($objectType));
					if (!is_object($element) || !is_a($element, $objectType)) return false;
					break;
				
				default:
					// As we validate type in the setter, this should be unreachable code
					assert(false);
			}
		}
		
		// All tests passed successfully
		return true;
	}

	//
	// Private methods
	//
	/**
	 * Return supported meta-data element types
	 * NB: PHP4 work-around for a private static class member
	 * @return array supported meta-data element types 
	 */
	function _getSupportedTypes() {
		static $_supportedTypes = array(
			METADATA_ELEMENT_TYPE_STRING,
			METADATA_ELEMENT_TYPE_DATE,
			METADATA_ELEMENT_TYPE_INTEGER,
			METADATA_ELEMENT_TYPE_OBJECT
		);
		return $_supportedTypes;
	}
		
	/**
	 * Return supported cardinalities
	 * NB: PHP4 work-around for a private static class member
	 * @return array supported cardinatlities 
	 */
	function _getSupportedCardinalities() {
		static $_supportedCardinalities = array(
			METADATA_ELEMENT_CARDINALITY_ONE,
			METADATA_ELEMENT_CARDINALITY_MANY
		);
		return $_supportedCardinalities;
	}
}
?>