<?php
/**
 * @file classes/filter/Filter.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Filter
 * @ingroup filter
 *
 * @brief Class that provides the basic template for a filter. Filters are
 *  generic data processors that take in a well-specified data type
 *  and return another well-specified data type.
 *
 *  Filters enable us to re-use data transformations between applications.
 *  Generic filter implementations can sequence, (de-)multiplex or iterate
 *  over other filters. Thereby filters can be nested and combined in many
 *  different ways to form complex and easy-to-customize data processing
 *  networks or pipelines.
 *
 *  NB: This also means that filters only make sense if they accept and
 *  return standardized formats that are understood by other filters. Otherwise
 *  the extra implementation effort for a filter won't result in improved code
 *  re-use.
 *
 *  Objects from different applications (e.g. Papers and Articles) can first be
 *  transformed by an application specific filter into a common format and then
 *  be processed by application agnostic import/export filters or vice versa.
 *  Filters can be used to pre-process data before it is indexed for search.
 *  They also provide a framework to customize the processing applied in citation
 *  parsing and lookup (i.e. which parsers and lookup sources should be applied).
 *
 *  Filters can be used stand-alone outside PKP applications.
 *
 *  The following is a complete list of all use-cases that have been identified
 *  for filters:
 *  1) Decode/Encode
 *  * import/export: transform application objects (e.g. an Article object)
 *    into structured (rich) data formats (e.g. XML, OpenURL KEV, CSV) or
 *    vice versa.
 *  * parse: transform unstructured clob/blob data (e.g. a Word Document)
 *    into application objects (e.g. an Article plus Citation objects) or
 *    into structured data formats (e.g. XML).
 *  * render: transform application objects or structured clob/blob data into
 *    an unstructured document (e.g. PDF, HTML, Word Document).
 *
 *  2) Normalize
 *  * lookup: compare the data of a given entity (e.g. a bibliographic
 *    reference) with data from other sources (e.g. CrossRef) and use this
 *    to normalize data or improve data quality.
 *  * harvest: cleanse and normalize incoming meta-data
 *
 *  3) Map
 *  * cross-walk: transform one meta-data format into another. Meta-data
 *    can be represented as structured clob/blob data (e.g. XML) or as
 *    application objects (i.e. a MetadataRecord instance).
 *  * meta-data extraction: retrieve meta-data from OO entities
 *    (e.g. an Article) into a standardized meta-data record (e.g. NLM
 *    element-citation).
 *  * meta-data injection: inject data from a standardized meta-data
 *    record into application objects.
 *
 *  4) Convert documents
 *  * binary converters: wrap binary document converters (e.g. antidoc) in
 *    a well-defined and re-usable way.
 *
 *  5) Search
 *  * indexing: pre-process data (extract, tokenize, remove stopwords,
 *    stem) for indexing.
 *  * finding: pre-process queries (parse, tokenize, remove stopwords,
 *    stem) to access the index
 */

// $Id$


class Filter {
	/** @var string */
	var $_displayName;

	/**
	 * Constructor
	 */
	function Filter() {
	}

	//
	// Setters and Getters
	//
	/**
	 * Set the display name
	 * @param $displayName string
	 */
	function setDisplayName($displayName) {
		$this->_displayName = $displayName;
	}

	/**
	 * Get the display name
	 * @return string
	 */
	function getDisplayName() {
		return $this->_displayName;
	}

	//
	// Abstract template methods to be implemented by subclasses
	//
	/**
	 * Returns true if the given input and output
	 * objects represent a valid transformation
	 * for this filter.
	 *
	 * This check must be type based. It can
	 * optionally include an additional stateful
	 * inspection of the given object instances.
	 *
	 * If the output type is null then only
	 * check whether the given input type is
	 * one of the input types accepted by this
	 * filter.
	 *
	 * @param $input mixed
	 * @param $output mixed
	 * @return boolean
	 */
	function supports(&$input, &$output) {
		assert(false);
	}

	/**
	 * This method performs the actual data processing.
	 * NB: sub-classes must implement this method.
	 * @param $input mixed validated filter input data
	 * @return mixed non-validated filter output or null
	 *  if processing was not successful.
	 */
	function &process(&$input) {
		assert(false);
	}

	//
	// Public methods
	//
	/**
	 * Returns true if the given input is supported
	 * by this filter. Otherwise returns false.
	 *
	 * NB: sub-classes will not normally override
	 * this method.
	 *
	 * @param $input mixed
	 * @return boolean
	 */
	function supportsAsInput(&$input) {
		$nullVar = null;
		return($this->supports($input, $nullVar));
	}

	/**
	 * Filters the given input.
	 *
	 * Input and output of this method will
	 * be tested for compliance with the filter
	 * definition.
	 *
	 * NB: sub-classes will not normally override
	 * this method.
	 *
	 * @param mixed an input value that is supported
	 *  by this filter
	 * @return mixed a valid return value or null
	 *  if an error occurred during processing
	 */
	function &execute(&$input) {
		// Validate the filter input
		if (!$this->supportsAsInput($input)) {
			$output = null;
			return $output;
		}

		// Process the filter
		$output =& $this->process($input);

		// Validate the filter output
		if (is_null($output) || !$this->supports($input, $output)) $output = null;

		// Return processed data
		return $output;
	}
}
?>