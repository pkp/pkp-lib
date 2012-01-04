<?php

/**
 * @file classes/filter/FilterRegistry.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FilterRegistry
 * @ingroup filter
 * @see Filter
 *
 * @brief Maintains a list of filter transformations.
 *
 * The filter registry allows filter consumers to identify available transformations
 * that convert a given input type into a required output type.
 *
 * Transformations are defined as a combination of a filter class and a pair of
 * input/output type specifications supported by that filter implementation.
 *
 * Input and output type specifications need to fulfill several requirements:
 * 1) They must uniquely and reliably identify the transformations supported
 *    by a filter.
 * 2) They must be flexible enough to deal with type polymorphism (e.g. a
 *    generic XSL filter may accept several XML input formats while a
 *    specialized crosswalk filter may only accept a very specific XML
 *    encoding as input.)
 * 3) A single filter may implement several transformations, i.e. distinct
 *    combinations of input and output types. Therefore the filter registry
 *    must be able to check all available transformations against a given input
 *    type and an expected output type and select those filters that support
 *    compatible transformations.
 * 4) Type definitions must be consistent over all filters (even cross-plugin).
 * 5) New filters can introduce new types at any time. We therefore cannot
 *    implement a static set (or vocabulary) of types.
 *
 * Additional requirements:
 * 1) The registry must take care to only select such transformations that are
 *    supported by the current runtime environment.
 * 2) The registry implementation must be performant and memory efficient.
 * 3) The registry must support static registration of core filters and dynamic
 *    registration for filters provided by plug-ins.
 *
 * Implementation decisions:
 * - Filters will be uniquely identified by their canonical class name. We chose
 *   the well established class name nomenclature used with the import() method.
 * - Input and output are typed according to their PHP class. There are several
 *   advantages to such an approach:
 *   * The PHP type system is well known to developers. This makes transformation
 *     definitions easily understandable.
 *   * We can re-use an existing and well understood type system that supports
 *     polymorphism.
 *   * We can use PHP's existing type checking functions which are highly
 *     optimized and very well tested. This makes type checking very easy to
 *     implement.
 *   * As filters have to work on PHP objects or types anyway the definition of
 *     input and output types by their PHP types comes in naturally and can be
 *     implemented with very little overhead in filter implementations.
 *   Disadvantages of using the PHP type system are:
 *   * PHP4 doesn't support interfaces or multiple inheritance which reduces the
 *     flexibility of the type system. (FIXME: When we drop PHP4 support we
 *     should implement multiple inheritance for input/output types based on
 *     interface hierarchies.)
 *   * In PHP basic types like strings, integers, booleans, floats, etc. are not
 *     implemented as classes. Checking these types will require additional
 *     programming logic.
 *   * PHP4 has very limited support for type reflection. Many PHP4 class handling
 *     methods work on object instances and not on type definitions. PHP does not
 *     represent classes as objects which makes abstract type checking more
 *     complicated to implement.
 *   * Types cannot represent the internal state of objects which may be relevant
 *     for the definition of the accepted input/output types of a transformation.
 *     To give an example: The MetadataSchema of a given MetadataDescription is
 *     not represented in it's object type. We have to inspect the internal state
 *     of the MetadataDescription object to determine whether it complies to a
 *     certain meta-data schema.
 * - To support use-cases as the one just described we allow filters to (optionally)
 *   inspect internal object state to determine whether a given input/output
 *   combination is really supported by the filter. We however assume that a
 *   transformation can be uniquely identified by the name of the filter that
 *   implements it and the given input and output types, i.e. their class names,
 *   even if the filter performs stateful inspection. In other words: The registry
 *   can only register several transformations for a single filter if their input
 *   and output class combinations differ. (FIXME: It might become necessary to
 *   include a textual representation of stateful inspection into our transformation
 *   type identifiers if type names are not granular enough.)
 * - As we use textual representations to define transformations (i.e. class name,
 *   input/output type names) we only have to instantiate filters for stateful
 *   inspection. Filters that support incompatible class types can be excluded
 *   from the result set without even having to load their class definition.
 */

// $Id$

class FilterRegistry {
	/**
	 * @var array A list of all registered transformations that maps the
	 *  transformation id to the filter class name.
	 */
	var $_registeredTransformations = array();

	/**
	 * @var array Map a transformation id to a translation key that
	 *  describes the transformation to the end user.
	 */
	var $_displayNameMap = array();

	/**
	 * @var array List where runtime requirements can be looked up
	 *  for a given transformation.
	 *  The key of this array is a concatenation of the filter name
	 *  and the input/output type specifications.
	 */
	var $_runtimeRequirements = array();

	/** @var array Maps input types to an array of supported Filters. */
	var $_inputTypeMap = array();

	/** @var array Maps output types to an array of supported Filters. */
	var $_outputTypeMap = array();

	/**
	 * Constructor
	 */
	function FilterRegistry() {
	}

	//
	// Public methods
	//
	/**
	 * Register a filter transformation with the system. This method
	 * can be called several times for every filter with different
	 * input and output types.
	 *
	 * NB: In principle the input and output types would not be
	 * necessary for filter selection as Filter classes' static
	 * supports*() methods fully specify the supported transformations.
	 * We require textual input and output type specification to
	 * be able to implement late loading and filter instantiation.
	 * This considerably improves filter registration performance
	 * and memory footprint. We also require these type parameters
	 * to register separate display names for all transformations
	 * even if they are based on the same filter class.
	 *
	 * FIXME: We might have to include stateful inspection properties
	 * into the input/output type definition if type based specification
	 * is not granular enough.
	 *
	 * FIXME: In the future we want to support configurable filters.
	 * We then have to rename $filterClassName to $filterInstanceName
	 * and provide a configuration source for filter instances. The
	 * configuration source could use some kind of dependency injection
	 * framework to configure filter instances e.g. from an XML file.
	 *
	 * @param $filterClassName string The (unique) class name of the Filter
	 *  to register. Corresponds to a string that can be resolved by the
	 *  import() function.
	 * @param $inputType string The input class of the transformation, primitive
	 *  PHP types must be prefixed with 'primitive::', example: 'primitive::string',
	 *  arrays of types can be declared with '[]', example: 'MyClass[]'. The give
	 *  type of an array that contains heterogeneous types is the type of the
	 *  first entry in the array. This will be used for type-based transformation
	 *  candidate pre-selection.
	 * @param $outputType string The output class of the transformation, see
	 *  use of 'primitive::' prefix and array declarations above.
	 * @param $displayName string A translation string that resolves
	 *  to the display name that represents the registered transformation.
	 * @param $requiredRuntimeEnvironment RuntimeEnvironment specifies
	 *  runtime requirements for the transformation
	 */
	function &registerTransformation($filterClassName, $inputType, $outputType, $displayName, $requiredRuntimeEnvironment = null) {
		// Construct the unique transformation ID
		$transformationId = $filterClassName.'-'.$inputType.'-'.$outputType;

		// Make sure that no such transformation has been
		// registered before.
		assert(!isset($this->_registeredTransformations[$transformationId]));

		// Register filter name, display name and runtime environment restrictions
		$this->_registeredTransformations[$transformationId] = $filterClassName;
		$this->_displayNameMap[$transformationId] = $displayName;
		$this->_runtimeRequirements[$transformationId] = $requiredRuntimeEnvironment;

		// Create an entry for the transformation in the input and output type maps
		$this->_inputTypeMap[$inputType] = $transformationId;
		$this->_outputTypeMap[$outputType] = $transformationId;
	}

	/**
	 * Retrieve transformations that support a given input and
	 * output sample object.
	 *
	 * The selection of filters that are compatible with the
	 * given input and output samples is based on type (class
	 * hierarchy) checks and stateful inspection of the
	 * sample objects.
	 *
	 * The given objects must contain just enough state (if
	 * any at all) for filter candidates to be able to decide
	 * during stateful inspection whether they support object
	 * instances with the same state.
	 *
	 * FIXME: Extend type checks to interfaces once we drop
	 * PHP4 support.
	 *
	 * @param $inputSample mixed
	 * @param $outputSample mixed
	 * @return array an array of filter object instances with
	 *  their display names set to the registered values for the
	 *  transformation corresponding to the requested input/output
	 *  type definition.
	 */
	function &retrieveCompatibleTransformations(&$inputStub, &$outputStub) {
		// 1) Type check
		// Retrieve transformation candidates for the given
		// input and output types.
		$inputCandidates =& $this->_retrieveTransformationCandidates($inputStub, $this->_inputTypeMap);
		$outputCandidates =& $this->_retrieveTransformationCandidates($outputStub, $this->_outputTypeMap);

		// The intersection of both result sets gives us a preliminary
		// shortlist of transformation candidates.
		// FIXME: If this is not selective enough we may encode a
		// stateful inspection in future releases into the type
		// representation, e.g. 'Type->someField[state]'.
		$transformationCandidates = array_intersect($inputCandidates, $outputCandidates);

		// 2) Iterate over the shortlist for final filter selection and
		//    instantiation.
		$selectedFilters = array();
		foreach ($transformationCandidates as $candidateIndex => $transformationCandidate) {
			// 3) Check runtime environment requirements
			// Exclude filters that are not supported by the current
			// runtime environment. We check this before we load the
			// class definition to avoid potential parse errors and
			// performance overhead.
			$requiredRuntimeEnvironment = $this->_runtimeRequirements[$transformationCandidate];
			if (!is_null($requiredRuntimeEnvironment)) {
				assert(is_a($requiredRuntimeEnvironment, 'RuntimeRequirement'));
				if (!$requiredRuntimeEnvironment->isCompatible()) continue;
			}

			// 4) Stateful inspection
			// Instantiate filters and call their supports() method to
			// determine the final list of compatible filters.
			$filterClassAndPackage = $this->_registeredTransformations[$transformationCandidate];
			$filterClassAndPackageParts = explode('.', $filterClassAndPackage);
			$filterClass = array_pop($filterClassAndPackageParts);
			import($filterClassAndPackage);
			$filterInstance = new $filterClass();
			if ($filterInstance->supports($inputStub, $outputStub)) {
				// Set the filter's display name to the value defined for this transformation
				$filterInstance->setDisplayName($this->_displayNameMap[$transformationCandidate]);

				// Add the filter instance to the final result list
				$selectedTransformations[] = $filterInstance;
			}
		}

		// Return the list of selected filters
		return $selectedTransformations;
	}

	//
	// Private helper methods
	//
	/**
	 * Retrieves the transformation candidates for a given stub type.
	 * @param $stub mixed
	 * @param $map array the transformation map
	 * @return array a list of transformation ids
	 */
	function &_retrieveTransformationCandidates(&$stub, &$map) {
		// 1) Identify the type of the stub
		// Array handling
		if (is_array($stub)) {
			// If the stub is an array then we'll use the first
			// entry of the array as an indicator for transformation
			// candidate selection. Checks for heterogeneous arrays
			// must be done by stateful inspection of the array.
			$arraySuffix = '[]';
			assert(count($stub));
			$stubType = $stub[0];
		} else {
			$arraySuffix = '';
		}

		// Distinguish between objects and primitive types
		if ($isObject = is_object($stub)) {
			$stubType = get_class($stub);
		} else {
			$stubType = $this->_getPrimitiveTypeName($stub);
		}

		// 2) Iterate over the type and all of its parent types and
		//    retrieve the transformation candidates of all types.
		$transformationCandidates = array();
		while($stubType !== false) {
			// Add the entries in the registry for the type
			// to the candidate result set (if any).
			if (isset($map[$stubType])) {
				$transformationCandidates = array_merge($transformationCandidates, $map[$stubType.$arraySuffix]);
			}
			if ($isObject) {
				$stubType = get_parent_class($stubType);
			} else {
				$stubType = false;
			}
		}

		return $transformationCandidates;
	}

	/**
	 * Return a string representation of a primitive type.
	 * @param $variable mixed
	 */
	function _getPrimitiveTypeName(&$variable) {
		assert(!(is_object($variable) || is_array($variable) || is_null($variable)));

		// FIXME: When gettype's implementation changes as mentioned
		// in <http://www.php.net/manual/en/function.gettype.php> then
		// we have to manually re-implement this method.
		return str_replace('double', 'float', gettype($variable));
	}
}
?>
