<?php

/**
 * @file classes/metadata/EliminatingCrosswalkFilter.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EliminatingCrosswalkFilter
 * @ingroup metadata
 *
 * @brief Simple crosswalk filter that eliminates all meta-data
 *  description properties from the source description that are
 *  not part of the target description's schema.
 */

// $Id$

import('metadata.CrosswalkFilter');

class EliminatingCrosswalkFilter extends CrosswalkFilter {
	/**
	 * Constructor
	 */
	function EliminatingCrosswalkFilter() {
		// We allow any combination of meta-data schemas.
		parent::CrosswalkFilter('MetadataSchema', 'MetadataSchema');
	}

	//
	// Implement template methods from Filter
	//
	/**
	 * This implementation of the CrosswalkFilter
	 * simply removes statements from the incoming meta-data
	 * description that are not in the target description's schema.
	 * @see Filter::process()
	 * @param $input MetadataDescription
	 * @return MetadataDescription
	 */
	function &process(&$input) {
		// Create the target description
		$output = new MetadataDescription($this->_toSchema);

		// Compare the property names of the incoming description with
		// the property names allowed in the target schema.
		$sourceProperties = $input->getSetPropertyNames();
		$targetProperties = $output->getPropertyNames();
		$propertiesToBeRemoved = array_diff($sourceProperties, $targetProperties);

		// Remove statements for properties that are not in the target schema.
		$statements =& $input->getStatements();
		foreach($propertiesToBeRemoved as $propertyToBeRemoved) {
			assert(isset($statements[$propertyToBeRemoved]));
			unset($statements[$propertyToBeRemoved]);
		}

		// Set the remaining statements in the target description
		$success = $output->setStatements($statements);
		assert($success);

		return $output;
	}
}
?>