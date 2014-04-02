<?php

/**
 * @file classes/controllers/grid/GridDataProvider.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GridDataProvider
 * @ingroup classes_controllers_grid
 *
 * @brief Provide access to grid data.
 */

class GridDataProvider {
	/** @var array */
	var $_authorizedContext;


	/**
	 * Constructor
	 */
	function GridDataProvider() {
	}


	//
	// Getters and Setters
	//
	/**
	 * Set the authorized context once it
	 * is established.
	 * @param $authorizedContext array
	 */
	function setAuthorizedContext(&$authorizedContext) {
		$this->_authorizedContext =& $authorizedContext;
	}

	/**
	 * Retrieve an object from the authorized context
	 * @param $assocType integer
	 * @return mixed will return null if the context
	 *  for the given assoc type does not exist.
	 */
	function &getAuthorizedContextObject($assocType) {
		if ($this->hasAuthorizedContextObject($assocType)) {
			return $this->_authorizedContext[$assocType];
		} else {
			$nullVar = null;
			return $nullVar;
		}
	}

	/**
	 * Check whether an object already exists in the
	 * authorized context.
	 * @param $assocType integer
	 * @return boolean
	 */
	function hasAuthorizedContextObject($assocType) {
		return isset($this->_authorizedContext[$assocType]);
	}


	//
	// Template methods to be implemented by subclasses
	//
	/**
	 * Get the authorization policy.
	 * @param $request PKPRequest
	 * @param $args array
	 * @param $roleAssignments array
	 * @return PolicySet
	 */
	function getAuthorizationPolicy(&$request, $args, $roleAssignments) {
		assert(false);
	}

	/**
	 * Get an array with all request parameters
	 * necessary to uniquely identify the data
	 * selection of this data provider.
	 * @return array
	 */
	function getRequestArgs() {
		assert(false);
	}

	/**
	 * Retrieve the data to load into the grid.
	 * @return array
	 */
	function &loadData() {
		assert(false);
	}
}

?>
