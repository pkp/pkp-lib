<?php
/**
 * @file classes/filter/GenericMultiplexerFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GenericMultiplexerFilter
 * @ingroup filter
 *
 * @brief A generic filter that is configured with a number of
 *  equal type filters. It takes the input argument, applies all
 *  given filters to it and returns an array of outputs as a result.
 *
 *  The result can then be sent to either an iterator filter or
 *  to a de-multiplexer filter.
 */

import('lib.pkp.classes.filter.CompositeFilter');

class GenericMultiplexerFilter extends CompositeFilter {
	/**
	 * Constructor
	 */
	function GenericMultiplexerFilter($displayName = null, $transformation = null) {
		parent::CompositeFilter($displayName, $transformation);
	}

	//
	// Implementing abstract template methods from Filter
	//
	/**
	 * @see Filter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.classes.filter.GenericMultiplexerFilter';
	}

	/**
	 * @see Filter::process()
	 * @param $input mixed
	 * @return array
	 */
	function &process(&$input) {
		// Iterate over all filters and return the results
		// as an array.
		$output = array();
		foreach($this->getFilters() as $filter) {
			// Make a copy of the input so that the filters don't interfere
			// with each other.
			if (is_object($input)) {
				$clonedInput =& cloneObject($input);
			} else {
				$clonedInput = $input;
			}

			// Execute the filter
			$output[] =& $filter->execute($clonedInput);

			// Propagate errors of sub-filters (if any)
			foreach($filter->getErrors() as $errorMessage) $this->addError($errorMessage);

			unset ($clonedInput);
		}
		return $output;
	}
}
?>
