<?php
/**
 * @file classes/filter/GenericMultiplexerFilter.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
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

// $Id$

import('filter.Filter');

class GenericMultiplexerFilter extends Filter {
	/** @var array An unordered array of filters that we run the input over */
	var $_filters = array();

	/**
	 * Constructor
	 */
	function GenericMultiplexerFilter() {
		parent::Filter();
	}

	//
	// Public methods
	//
	/**
	 * Adds a filter to the filter list.
	 * @param $filter Filter
	 */
	function addFilter(&$filter) {
		assert(is_a($filter, 'Filter'));
		$this->_filters[] =& $filter;
	}

	//
	// Implementing abstract template methods from Filter
	//
	/**
	 * @see Filter::supports()
	 * @param $input mixed
	 * @param $output mixed
	 * @return boolean
	 */
	function supports(&$input, &$output) {
		// Preliminary check: do we have filters at all?
		if(!count($this->_filters)) return false;

		if (!is_null($output)) {
			// Pre-check the number of the output objects
			if (!is_array($output) || count($output) != count($this->_filters)) return false;
		}

		// Iterate over the filters and check the
		// corresponding inputs and outputs.
		foreach($this->_filters as $outputNumber => $filter) {
			$currentOutput = (is_null($output) ? null : $output[$outputNumber]);
			if (!$filter->supports($input, $currentOutput)) return false;
		}

		// If all individual filter validations passed then this
		// filter supports the given data
		return true;
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
		foreach($this->_filters as $filter) {
			// Make a copy of the input so that the filters don't interfere
			// with each other.
			if (is_object($input)) {
				$clonedInput =& cloneObject($input);
			} else {
				$clonedInput = $input;
			}

			$output[] = $filter->execute($clonedInput);
			unset ($clonedInput);
		}
		return $output;
	}
}
?>