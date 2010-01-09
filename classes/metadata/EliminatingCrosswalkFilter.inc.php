<?php

/**
 * @file classes/metadata/EliminatingCrosswalkFilter.inc.php
 *
 * Copyright (c) 2000-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EliminatingCrosswalkFilter
 * @ingroup metadata
 * @see MetadataRecord
 *
 * @brief Class that provides methods to convert one type of
 *  meta-data record into another. This is an abstract template
 *  class that should be sub-classed by specific cross-walk
 *  implementations.
 */

// $Id$

import('metadata.CrosswalkFilter');

class EliminatingCrosswalkFilter extends CrosswalkFilter {
	/**
	 * This implementation of the CrosswalkFilter
	 * simply removes statements from the incoming meta-data
	 * record that are not in the target schema.
	 * @see Filter::process()
	 * @param $input MetadataRecord
	 * @return MetadataRecord
	 */
	function process(&$input) {
		// Create the target record
		$output =& new MetadataRecord($this->_toSchema);

		// Compare the property names of the incoming record with
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

		// Set the remaining statements in the target record
		$success = $output->setStatements($statements);
		assert($success);

		return $output;
	}
}
?>