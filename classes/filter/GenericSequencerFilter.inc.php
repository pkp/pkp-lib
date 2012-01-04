<?php
/**
 * @file classes/filter/GenericSequencerFilter.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GenericSequencerFilter
 * @ingroup filter
 *
 * @brief A generic filter that is configured with a number of
 *  ordered filters. It takes the input argument of the first filter,
 *  passes its output to the next filter and so on and finally returns
 *  the result of the last filter in the chain to the caller.
 */

// $Id$

import('filter.Filter');

class GenericSequencerFilter extends Filter {
	/** @var array An array of filters that we run in order */
	var $_filters = array();

	/** @var array test objects required for filter chain validation */
	var $_intermediateResultSamples = array();

	/**
	 * Constructor
	 */
	function GenericSequencerFilter() {
		parent::Filter();
	}

	//
	// Public methods
	//
	/**
	 * Adds a filter to the end of the
	 * filter list.
	 * @param $filter Filter
	 * @param $inputSample mixed a test object that validates as input against
	 *  the supports() function of the added filter and also has to be supported
	 *  as output of the previously added filter (if any). This will be used
	 *  to validate the filter sequence.
	 */
	function addFilter(&$filter, &$inputSample) {
		assert(is_a($filter, 'Filter'));
		assert(!is_null($inputSample));

		// The sample must be supported as input by the added
		// filter
		assert($filter->supportsAsInput($inputSample));

		// The sample must be supported as output by the
		// previously added filter (if there is any).
		$previouslyAddedFilterId = count($this->_filters)-1;
		if ($previouslyAddedFilterId >= 0) {
			$previousFilter =& $this->_filters[$previouslyAddedFilterId];
			$previousInputSample =& $this->_intermediateResultSamples[$previouslyAddedFilterId];
			assert($previousFilter->supports($previousInputSample, $inputSample));
		}

		// Store filter and sample data
		$this->_intermediateResultSamples[] =& $inputSample;
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
		$nullVar = null;

		// The input must be validated by the first filter
		// in the sequence.
		$firstFilter =& $this->_filters[0];
		if (!$firstFilter->supports($input, $nullVar)) return false;

		// The output must be validated by the last filter
		// in the sequence.
		$lastFilterId = count($this->_filters)-1;
		$lastFilter =& $this->_filters[$lastFilterId];
		$inputSample =& $this->_intermediateResultSamples[$lastFilterId];
		return $lastFilter->supports($inputSample, $output);
	}

	/**
	 * @see Filter::process()
	 * @param $input mixed
	 * @return mixed
	 */
	function &process(&$input) {
		// Iterate over all filters and always feed the
		// output of one filter as input to the next
		// filter.
		$previousOutput = null;
		foreach($this->_filters as $filter) {
			if(is_null($previousOutput)) {
				// First filter
				$previousOutput =& $input;
			}
			$output = $filter->execute($previousOutput);

			// If one filter returns null then we'll abort
			// execution of the filter chain.
			if (is_null($output)) break;

			unset($previousOutput);
			$previousOutput = $output;
		}
		return $output;
	}
}
?>