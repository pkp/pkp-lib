<?php

/**
 * @file classes/metadata/MetadataProperty.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
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

	/** @var integer property type */
	var $_type;

	/** @var integer association type of a composite type */
	var $_compositeType;

	/** @var boolean flag that defines whether the property can be translated */
	var $_translated;

	/** @var integer property cardinality */
	var $_cardinality;

	/**
	 * Constructor
	 * @param $name string the unique name of the property within a meta-data schema (can be a property URI)
	 * @param $assocTypes array an array of integers that define the application entities that can
	 *  be described with this property.
	 * @param $type integer must be one of the supported types, default: METADATA_PROPERTY_TYPE_STRING
	 * @param $translated boolean whether the property may have various language versions, default: false
	 * @param $cardinality integer must be on of the supported cardinalities, default: METADATA_PROPERTY_CARDINALITY_ONE
	 * @param $compositeType integer an association type, mandatory if $type is METADATA_PROPERTY_TYPE_COMPOSITE
	 */
	function MetadataProperty($name, $assocTypes = array(), $type = METADATA_PROPERTY_TYPE_STRING,
			$translated = false, $cardinality = METADATA_PROPERTY_CARDINALITY_ONE, $compositeType = null, $displayName = null) {

		// Validate input data
		assert(is_array($assocTypes));
		assert(in_array($type, MetadataProperty::getSupportedTypes()));
		assert(in_array($cardinality, MetadataProperty::getSupportedCardinalities()));
		if ($type == METADATA_PROPERTY_TYPE_COMPOSITE) {
			assert(!$translated);
			assert(isset($compositeType) && is_integer($compositeType));
		} else {
			assert(is_null($compositeType));
		}

		// Initialize the class
		$this->_name = (string)$name;
		$this->_assocTypes =& $assocTypes;
		$this->_type = (integer)$type;
		$this->_compositeType = $compositeType;
		$this->_translated = (boolean)$translated;
		$this->_cardinality = (integer)$cardinality;

		// Default display name
		if (is_null($displayName)) $displayName = 'metadata.property.displayName.'.$this->_name;
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
	 * Get the type
	 * @return integer
	 */
	function getType() {
		return $this->_type;
	}

	/**
	 * Get the composite type (for composite
	 * properties only)
	 * @return integer
	 */
	function getCompositeType() {
		return $this->_compositeType;
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
				// Composites can either be represented by a meta-data description
				// or by a string of the form AssocType:AssocId if the composite
				// has already been persisted in the database.
				switch(true) {
					case is_a($value, 'MetadataDescription'):
						$assocType = $value->getAssocType();
						break;

					case is_string($value):
						$valueParts = explode(':', $value);
						if (count($valueParts) != 2) return false;
						list($assocType, $assocId) = $valueParts;
						if (!is_numeric($assocId)) return false;
						break;

					default:
						// None of the allowed types
						return false;
				}

				// Check that the association type matches
				if ($assocType != $this->_compositeType) return false;
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