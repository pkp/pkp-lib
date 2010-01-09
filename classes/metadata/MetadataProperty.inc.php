<?php

/**
 * @file classes/metadata/MetadataProperty.inc.php
 *
 * Copyright (c) 2000-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MetadataProperty
 * @ingroup metadata
 * @see MetadataSchema
 * @see MetadataRecord
 *
 * @brief Class representing metadata properties. It specifies type and cardinality
 *  of a meta-data property (=term, field, ...) and whether the property can
 *  be internationalized. It also provides a validator to test whether input
 *  conforms to the property specification.
 *
 *  In the DCMI abstract model, this class specifies a property together with its
 *  allowed range and cardinality.
 */

// $Id$

// literal values (plain)
define('METADATA_PROPERTY_TYPE_STRING', 0x01);

// literal values (typed)
define('METADATA_PROPERTY_TYPE_DATE', 0x02);
define('METADATA_PROPERTY_TYPE_INTEGER', 0x03);

// non-literal value string from a controlled vocabulary
define('METADATA_PROPERTY_TYPE_VOCABULARY', 0x04);

// non-literal value URI
define('METADATA_PROPERTY_TYPE_URI', 0x05);

// non-literal value pointing to a separate description set instance (=another MetadataRecord object)
define('METADATA_PROPERTY_TYPE_COMPOSITE', 0x06);

// allowed cardinality of statements for a given property type in a meta-data schema
define('METADATA_PROPERTY_CARDINALITY_ONE', 0x01);
define('METADATA_PROPERTY_CARDINALITY_MANY', 0x02);

class MetadataProperty {
	/** @var string property name */
	var $_name;

	/** @var integer property type */
	var $_type;

	/** @var boolean flag that defines whether the property can be translated */
	var $_translated;

	/** @var integer property cardinality */
	var $_cardinality;

	/**
	 * Constructor
	 * @param $name string the unique name of the property within a meta-data schema (can be a property URI)
	 * @param $type integer must be one of the supported types, default: METADATA_PROPERTY_TYPE_STRING
	 * @param $translated boolean whether the property may have various language versions, default: false
	 * @param $cardinality integer must be on of the supported cardinalities, default: METADATA_PROPERTY_CARDINALITY_ONE
	 */
	function MetadataProperty($name, $type = METADATA_PROPERTY_TYPE_STRING,
			$translated = false, $cardinality = METADATA_PROPERTY_CARDINALITY_ONE) {

		// Initialize the class
		$this->setName($name);
		$this->setType($type);
		$this->setTranslated($translated);
		$this->setCardinality($cardinality);
	}

	/**
	 * Get the name
	 * @return string
	 */
	function getName() {
		return $this->_name;
	}

	/**
	 * Set the name
	 * @param $name string
	 */
	function setName($name) {
		$this->_name = (string)$name;
	}

	/**
	 * Get the type
	 * @return integer
	 */
	function getType() {
		return $this->_type;
	}

	/**
	 * Set the type
	 * @param $type integer
	 */
	function setType($type) {
		assert(in_array($type, MetadataProperty::_getSupportedTypes()));
		$this->_type = (integer)$type;
	}

	/**
	 * Is this property translated?
	 * @return boolean
	 */
	function getTranslated() {
		return $this->_translated;
	}

	/**
	 * Define whether this property is translated
	 * @param $translated boolean
	 */
	function setTranslated($translated) {
		$this->_translated = (boolean)$translated;
	}

	/**
	 * Get the cardinality
	 * @return integer
	 */
	function getCardinality() {
		return $this->_cardinality;
	}

	/**
	 * Set the cardinality
	 * @param $cardinality integer
	 */
	function setCardinality($cardinality) {
		assert(in_array($cardinality, MetadataProperty::_getSupportedCardinalities()));
		$this->_cardinality = (integer)$cardinality;
	}

	/**
	 * Validate a given input against the property specification
	 *
	 * NB: We could invent some "MetaDataPropertyValidator" classes to
	 * modularize type- or cardinality-specific validation. But we don't
	 * as long as we have just a few types and simple validation
	 * algorithms.
	 *
	 * @param $value mixed the input to be validated
	 * @return boolean validation success
	 */
	function isValid($value) {
		// We never accept null values or arrays.
		if (is_null($value) || is_array($value)) return false;

		// FIXME: see #5023
		import('form.Form');
		$form = new Form('');

		// Validate type
		switch ($this->getType()) {
			case METADATA_PROPERTY_TYPE_STRING:
				if (!is_string($value)) return false;
				break;

			case METADATA_PROPERTY_TYPE_VOCABULARY:
				// Interpret the name of this property as a controlled vocabulary triple
				$vocabNameParts = explode(':', $this->getName());
				assert(count($vocabNameParts) == 3);
				list($symbolic, $assocType, $assocId) = $vocabNameParts;

				// Re-use the controlled vocabulary form validator
				// FIXME: see #5023
				$form->setData('property', $value);
				import('form.validation.FormValidatorControlledVocab');
				$validator = new FormValidatorControlledVocab($form, 'property', 'required', '', $symbolic, $assocType, $assocId);
				if (!$validator->isValid()) return false;
				break;

			case METADATA_PROPERTY_TYPE_URI:
				// Re-use the URI form validator
				// FIXME: see #5023
				$form->setData('property', $value);
				import('form.validation.FormValidatorUri');
				$validator = new FormValidatorUri($form, 'property', 'required', '');
				if (!$validator->isValid()) return false;
				break;

			case METADATA_PROPERTY_TYPE_DATE:
				// We allow the following patterns:
				// YYYY-MM-DD, YYYY-MM and YYYY
				$datePattern = '/^[0-9]{4}(-[0-9]{2}(-[0-9]{2})?)?$/';
				if (!preg_match($datePattern, $value)) return false;

				// Check whether the given string is really a valid date
				$dateParts = explode('-', $value);
				// Set the day and/or month to 1 if not set
				$dateParts = array_pad($dateParts, 3, 1);
				// Extract the date parts
				list($year, $month, $day) = $dateParts;
				// Validate the date (only leap days will pass unnoticed ;-) )
				// Who invented this argument order?
				if (!checkdate($month, $day, $year)) return false;
				break;

			case METADATA_PROPERTY_TYPE_INTEGER:
				if (!is_integer($value)) return false;
				break;

			case METADATA_PROPERTY_TYPE_COMPOSITE:
				if (!is_object($value) || !is_a($value, 'MetadataRecord')) return false;
				break;

			default:
				// As we validate type in the setter, this should be unreachable code
				assert(false);
		}

		// Successful validation
		return true;
	}

	//
	// Private methods
	//
	/**
	 * Return supported meta-data property types
	 * NB: PHP4 work-around for a private static class member
	 * @return array supported meta-data property types
	 */
	function _getSupportedTypes() {
		static $_supportedTypes = array(
			METADATA_PROPERTY_TYPE_STRING,
			METADATA_PROPERTY_TYPE_DATE,
			METADATA_PROPERTY_TYPE_INTEGER,
			METADATA_PROPERTY_TYPE_VOCABULARY,
			METADATA_PROPERTY_TYPE_URI,
			METADATA_PROPERTY_TYPE_COMPOSITE
		);
		return $_supportedTypes;
	}

	/**
	 * Return supported cardinalities
	 * NB: PHP4 work-around for a private static class member
	 * @return array supported cardinalities
	 */
	function _getSupportedCardinalities() {
		static $_supportedCardinalities = array(
			METADATA_PROPERTY_CARDINALITY_ONE,
			METADATA_PROPERTY_CARDINALITY_MANY
		);
		return $_supportedCardinalities;
	}
}
?>