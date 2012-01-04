<?php

/**
 * @file classes/metadata/MetadataProperty.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
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
 *
 *  We also define the resource types (application entities, association types)
 *  that can be described with the property. This allows us to check that only
 *  valid resource associations are made. It also allows us to prepare property
 *  entry forms or displays for a given resource type and integrate these in the
 *  work-flow of the resource. By dynamically adding or removing assoc types,
 *  end users will be able to configure the meta-data fields that they wish to
 *  make available, persist or enter in their application.
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

	/** @var string a translation id */
	var $_displayName;

	/** @var int the resource types that can be described with this property */
	var $_assocTypes;

	/** @var array allowed property types */
	var $_types;

	/** @var boolean flag that defines whether the property can be translated */
	var $_translated;

	/** @var integer property cardinality */
	var $_cardinality;

	/**
	 * Constructor
	 * @param $name string the unique name of the property within a meta-data schema (can be a property URI)
	 * @param $assocTypes array an array of integers that define the application entities that can
	 *  be described with this property.
	 * @param $types mixed must be a scalar or an array with the supported types, default: METADATA_PROPERTY_TYPE_STRING
	 * @param $translated boolean whether the property may have various language versions, default: false
	 * @param $cardinality integer must be on of the supported cardinalities, default: METADATA_PROPERTY_CARDINALITY_ONE
	 * @param $compositeType integer an association type, mandatory if $type is METADATA_PROPERTY_TYPE_COMPOSITE
	 */
	function MetadataProperty($name, $assocTypes = array(), $types = METADATA_PROPERTY_TYPE_STRING,
			$translated = false, $cardinality = METADATA_PROPERTY_CARDINALITY_ONE, $displayName = null) {

		// Validate name and assoc type array
		assert(is_string($name));
		assert(is_array($assocTypes));

		// A single type (scalar or composite) will be
		// transformed to an array of types so that we
		// can treat them uniformly.
		if (is_scalar($types) || count($types) == 1) {
			$types = array($types);
		}

		// Validate types
		foreach($types as $type) {
			if (is_array($type)) {
				// Validate composite types
				assert(count($type) == 1 && isset($type[METADATA_PROPERTY_TYPE_COMPOSITE]) && is_integer($type[METADATA_PROPERTY_TYPE_COMPOSITE]));
				// Properties that allow composite types cannot be translated
				assert(!$translated);
			} else {
				// Validate all other types
				assert($type != METADATA_PROPERTY_TYPE_COMPOSITE && in_array($type, MetadataProperty::getSupportedTypes()));
			}
		}

		// Validate translation and cardinality
		assert(is_bool($translated));
		assert(in_array($cardinality, MetadataProperty::getSupportedCardinalities()));

		// Default display name
		if (is_null($displayName)) $displayName = 'metadata.property.displayName.'.$name;
		assert(is_string($displayName));

		// Initialize the class
		$this->_name = (string)$name;
		$this->_assocTypes =& $assocTypes;
		$this->_types =& $types;
		$this->_translated = (boolean)$translated;
		$this->_cardinality = (integer)$cardinality;
		$this->_displayName = (string)$displayName;
	}

	/**
	 * Get the name
	 * @return string
	 */
	function getName() {
		return $this->_name;
	}

	/**
	 * Returns a canonical form of the property
	 * name ready to be used as a property id in an
	 * external context (e.g. Forms or Templates).
	 * @return string
	 */
	function getId() {
		// Replace special characters in XPath-like names
		// as 'person-group[@person-group-type="author"]'.
		$from = array(
			'[', ']', '@', '"', '='
		);
		$to = array(
			'-', '', '', '', '-'
		);
		$propertyId = trim(str_replace($from, $to, $this->getName()), '-');
		$propertyId = String::camelize($propertyId);
		return $propertyId;
	}

	/**
	 * Get the translation id representing
	 * the display name of the property.
	 * @return string
	 */
	function getDisplayName() {
		return $this->_displayName;
	}

	/**
	 * Get the allowed association types
	 * (resources that can be described
	 * with this property)
	 * @return array a list of integers representing
	 *  association types.
	 */
	function &getAssocTypes() {
		return $this->_assocTypes;
	}

	/**
	 * Get the allowed type
	 * @return integer
	 */
	function getTypes() {
		return $this->_types;
	}

	/**
	 * Is this property translated?
	 * @return boolean
	 */
	function getTranslated() {
		return $this->_translated;
	}

	/**
	 * Get the cardinality
	 * @return integer
	 */
	function getCardinality() {
		return $this->_cardinality;
	}

	//
	// Public methods
	//
	/**
	 * Validate a given input against the property specification
	 *
	 * @param $value mixed the input to be validated
	 * @return boolean validation success
	 */
	function isValid($value) {
		// We never accept null values or arrays.
		if (is_null($value) || is_array($value)) return false;

		// The value must validate against at least one type
		$isValid = false;
		foreach ($this->getTypes() as $type) {
			// Extract data from composite type
			if (is_array($type)) {
				assert(count($type) == 1 && key($type) == METADATA_PROPERTY_TYPE_COMPOSITE);
				$compositeType = $type[METADATA_PROPERTY_TYPE_COMPOSITE];
				$type = METADATA_PROPERTY_TYPE_COMPOSITE;
			}

			// Type specific validation
			switch ($type) {
				case METADATA_PROPERTY_TYPE_STRING:
					if (is_string($value)) $isValid = true;
					break;

				case METADATA_PROPERTY_TYPE_VOCABULARY:
					// Interpret the name of this property as a controlled vocabulary triple
					$vocabNameParts = explode(':', $this->getName());
					assert(count($vocabNameParts) == 3);
					list($symbolic, $assocType, $assocId) = $vocabNameParts;

					// Validate with controlled vocabulary validator
					import('validation.ValidatorControlledVocab');
					$validator = new ValidatorControlledVocab($symbolic, $assocType, $assocId);
					if ($validator->isValid($value)) $isValid = true;
					break;

				case METADATA_PROPERTY_TYPE_URI:
					// Validate with the URI validator
					import('validation.ValidatorUri');
					$validator = new ValidatorUri();
					if ($validator->isValid($value)) $isValid = true;
					break;

				case METADATA_PROPERTY_TYPE_DATE:
					// We allow the following patterns:
					// YYYY-MM-DD, YYYY-MM and YYYY
					$datePattern = '/^[0-9]{4}(-[0-9]{2}(-[0-9]{2})?)?$/';
					if (!preg_match($datePattern, $value)) break;

					// Check whether the given string is really a valid date
					$dateParts = explode('-', $value);
					// Set the day and/or month to 1 if not set
					$dateParts = array_pad($dateParts, 3, 1);
					// Extract the date parts
					list($year, $month, $day) = $dateParts;
					// Validate the date (only leap days will pass unnoticed ;-) )
					// Who invented this argument order?
					if (checkdate($month, $day, $year)) $isValid = true;
					break;

				case METADATA_PROPERTY_TYPE_INTEGER:
					if (is_integer($value)) $isValid = true;
					break;

				case METADATA_PROPERTY_TYPE_COMPOSITE:
					// Composites can either be represented by a meta-data description
					// or by a string of the form AssocType:AssocId if the composite
					// has already been persisted in the database.
					switch(true) {
						// Test for MetadataDescription format
						case is_a($value, 'MetadataDescription'):
							$assocType = $value->getAssocType();
							break;

						// Test for AssocType:AssocId format
						case is_string($value):
							$valueParts = explode(':', $value);
							if (count($valueParts) != 2) break 2; // break the outer switch
							list($assocType, $assocId) = $valueParts;
							if (!(is_numeric($assocType) && is_numeric($assocId))) break 2; // break the outer switch
							$assocType = (integer)$assocType;
							break;

						default:
							// None of the allowed types
							break;
					}

					// Check that the association type matches
					if (isset($assocType) && $assocType === $compositeType) $isValid = true;
					break;

				default:
					// Unknown type. As we validate type in the setter, this
					// should be unreachable code.
					assert(false);
			}

			// The value only has to validate against one of the given
			// types: No need to validate against subsequent allowed types.
			if ($isValid) break;
		}

		// Will return false if the value didn't validate against any
		// of the types, otherwise true.
		return $isValid;
	}

	//
	// Public static methods
	//
	/**
	 * Return supported meta-data property types
	 * NB: PHP4 work-around for a public static class member
	 * @return array supported meta-data property types
	 */
	function getSupportedTypes() {
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
	 * NB: PHP4 work-around for a public static class member
	 * @return array supported cardinalities
	 */
	function getSupportedCardinalities() {
		static $_supportedCardinalities = array(
			METADATA_PROPERTY_CARDINALITY_ONE,
			METADATA_PROPERTY_CARDINALITY_MANY
		);
		return $_supportedCardinalities;
	}
}
?>