<?php

/**
 * @file classes/metadata/CrosswalkFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CrosswalkFilter
 * @ingroup metadata
 * @see MetadataRecord
 *
 * @brief Class that provides methods to convert one type of
 *  meta-data record into another. This is an abstract template
 *  class that should be sub-classed by specific cross-walk
 *  implementations.
 */

// $Id$

import('filter.Filter');

class CrosswalkFilter extends Filter {
	/** @var MetadataSchema */
	var $_fromSchema;

	/** @var MetadataSchema */
	var $_toSchema;

	//
	// Getters and setters
	//
	/**
	 * Get the source meta-data schema
	 * @return MetadataSchema
	 */
	function &getFromSchema() {
		return $this->_fromSchema;
	}

	/**
	 * Set the source meta-data schema
	 * @param $fromSchema MetadataSchema
	 */
	function setFromSchema(&$fromSchema) {
		assert(is_a($fromSchema, 'MetadataSchema'));
		$this->_fromSchema =& $fromSchema;
	}

	/**
	 * Get the target meta-data schema
	 * @return MetadataSchema
	 */
	function &getToSchema() {
		return $this->_toSchema;
	}

	/**
	 * Set the target meta-data schema
	 * @param $toSchema MetadataSchema
	 */
	function setToSchema(&$toSchema) {
		assert(is_a($toSchema, 'MetadataSchema'));
		$this->_toSchema =& $toSchema;
	}

	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::supports()
	 * @param $input MetadataRecord
	 */
	function supports(&$input) {
		if (!is_a($input, 'MetadataRecord')) return false;
		$allowedSchema =& $this->_fromSchema;
		$inputSchema =& $input->getMetadataSchema();
		return ($inputSchema->getName() == $allowedSchema->getName());
	}

	/**
	 * @see Filter::isValid()
	 * @param $output MetadataRecord
	 */
	function isValid(&$output) {
		if (!is_a($input, 'MetadataRecord')) return false;
		$allowedSchema =& $this->_toSchema;
		$outputSchema =& $output->getMetadataSchema();
		return ($outputSchema->getName() == $allowedSchema->getName());
	}
}
?>