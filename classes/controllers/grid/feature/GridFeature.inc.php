<?php

/**
 * @file classes/controllers/grid/feature/GridFeature.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GridFeature
 * @ingroup controllers_grid_feature
 *
 * @brief Base grid feature class. A feature is a type of plugin specific
 * to the grid widgets. It provides several hooks to allow injection of
 * additional grid functionality. This class implements template methods
 * to be extendeded by subclasses.
 *
 */

class GridFeature {

	/** @var $id string */
	var $_id;

	/** @var $options array */
	var $_options;

	/**
	 * Constructor.
	 * @param $id string Feature id.
	 */
	function GridFeature($id) {
		$this->setId($id);
	}


	//
	// Getters and setters.
	//
	/**
	 * Get feature id.
	 * @return string
	 */
	function getId() {
		return $this->_id;
	}

	/**
	 * Set feature id.
	 * @param $id string
	 */
	function setId($id) {
		$this->_id = $id;
	}

	/**
	 * Get feature js class options.
	 * @return string
	 */
	function getOptions() {
		return $this->_options;
	}

	/**
	 * Add feature js class options.
	 * @param $options array $optionId => $optionValue
	 */
	function addOptions($options) {
		assert(is_array($options));
		$this->_options = array_merge((array) $this->getOptions(), $options);
	}


	//
	// Protected methods to be used or extended by subclasses.
	//
	/**
	 * Set feature js class options. Extend this method to
	 * define more feature js class options.
	 * @param $request Request
	 * @param $grid GridHandler
	 */
	function setOptions(&$request, &$grid) {
		$renderedElements = $this->fetchUIElements($grid, $request);
		if ($renderedElements) {
			foreach ($renderedElements as $id => $markup) {
				$this->addOptions(array($id => $markup));
			}
		}
	}

	/**
	 * Fetch any user interface elements that
	 * this feature needs to add its functionality
	 * into the grid.
	 * @param $grid GridHandler The grid that this
	 * feature is attached to.
	 * @return array It is expected that the array
	 * returns data in this format:
	 * $elementId => $elementMarkup
	 */
	function fetchUIElements(&$grid) {
		return array();
	}

	/**
	 * Return the java script feature class.
	 * @return string
	 */
	function getJSClass() {
		return null;
	}


	//
	// Public hooks to be implemented in subclasses.
	//
	/**
	 * Hook called every time grid initialize a row object.
	 * @param $args array Contains the initialized referenced row object
	 * in 'row' array index.
	 */
	function getInitializedRowInstance($args) {
		return null;
	}

	/**
	 * Hook called on grid category row initialization.
	 * @param $args array 'request' => Request
	 * 'grid' => CategoryGridHandler
	 * 'row' => GridCategoryRow
	 */
	function getInitializedCategoryRowInstance($args) {
		return null;
	}

	/**
	 * Hook called on grid's initialization.
	 * @param $args array Contains the grid handler referenced object
	 * in 'grid' array index.
	 */
	function gridInitialize($args) {
		return null;
	}

	/**
	 * Hook called on grid fetching.
	 * @param $args array 'grid' => GridHandler
	 */
	function fetchGrid($args) {
		$grid =& $args['grid'];
		$request =& $args['request'];

		$this->setOptions($request, $grid);
	}

	/**
	 * Hook called when save grid items sequence
	 * is requested.
	 * @param $args array 'request' => PKPRequest,
	 * 'grid' => GridHandler
	 */
	function saveSequence($args) {
		return null;
	}
}

?>
