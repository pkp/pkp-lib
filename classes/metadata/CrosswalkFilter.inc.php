<?php

/**
 * @file classes/metadata/CrosswalkFilter.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CrosswalkFilter
 * @ingroup metadata
 * @see MetadataDescription
 *
 * @brief Class that provides methods to convert one type of
 *  meta-data description into another. This is an abstract template
 *  class that should be sub-classed by specific cross-walk
 *  implementations.
 */

// $Id$

import('filter.Filter');

class CrosswalkFilter extends Filter {
	/** @var string */
	var $_fromSchema;

	/** @var string */
	var $_toSchema;

	/**
	 * Constructor
	 * @param $fromSchema string
	 * @param $toSchema string
	 */
	function CrosswalkFilter($fromSchema, $toSchema) {
		assert(class_exists($fromSchema) && class_exists($toSchema));
		$this->_fromSchema = $fromSchema;
		$this->_toSchema = $toSchema;
	}

	//
	// Getters and setters
	//
	/**
	 * Get the source meta-data schema class name
	 * @return string
	 */
	function getFromSchema() {
		return $this->_fromSchema;
	}

	/**
	 * Get the target meta-data schema class name
	 * @return MetadataSchema
	 */
	function getToSchema() {
		return $this->_toSchema;
	}

	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::supports()
	 * @param $input mixed
	 * @param $output mixed
	 */
	function supports(&$input, &$output) {
		// Validate input
		if (!$this->_complies($input, $this->getFromSchema())) return false;

		// Validate output
		if (is_null($output)) return true;
		return $this->_complies($output, $this->getToSchema());
	}

	//
	// Private helper methods
	//
	/**
	 * Checks whether a given description complies
	 * with a given meta-data schema class name.
	 * @param $metadataDescription MetadataDescription
	 * @param $schemaClassName string
	 * @return boolean
	 */
	function _complies(&$metadataDescription, $schemaClassName) {
		if (!is_a($metadataDescription, 'MetadataDescription')) return false;
		$descriptionSchema =& $metadataDescription->getMetadataSchema();
		return (is_a($descriptionSchema, $schemaClassName));
	}
}
?>