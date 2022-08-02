<?php

/**
 * @file classes/controllers/grid/GridDataProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GridDataProvider
 * @ingroup classes_controllers_grid
 *
 * @brief Grid data providers serve data to the grid classes for presentation
 *  in a grid.
 *
 * For general information about grids, see GridHandler.
 */

namespace PKP\controllers\grid;

class GridDataProvider
{
    /** @var array */
    public $_authorizedContext;


    /**
     * Constructor
     */
    public function __construct()
    {
    }


    //
    // Getters and Setters
    //
    /**
     * Set the authorized context once it
     * is established.
     *
     * @param array $authorizedContext
     */
    public function setAuthorizedContext(&$authorizedContext)
    {
        $this->_authorizedContext = & $authorizedContext;
    }

    /**
     * Retrieve an object from the authorized context
     *
     * @param int $assocType
     *
     * @return mixed will return null if the context
     *  for the given assoc type does not exist.
     */
    public function &getAuthorizedContextObject($assocType)
    {
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
     *
     * @param int $assocType
     *
     * @return bool
     */
    public function hasAuthorizedContextObject($assocType)
    {
        return isset($this->_authorizedContext[$assocType]);
    }


    //
    // Template methods to be implemented by subclasses
    //
    /**
     * Get the authorization policy.
     *
     * @param PKPRequest $request
     * @param array $args
     * @param array $roleAssignments
     *
     * @return PolicySet
     */
    public function getAuthorizationPolicy($request, $args, $roleAssignments)
    {
        throw new Exception('getRequestArgs called but not implemented!');
    }

    /**
     * Get an array with all request parameters
     * necessary to uniquely identify the data
     * selection of this data provider.
     *
     * @return array
     */
    public function getRequestArgs()
    {
        throw new Exception('getRequestArgs called but not implemented!');
    }

    /**
     * Retrieve the data to load into the grid.
     *
     * @param array $filter An optional associative array with filter data
     *  as returned by GridHandler::getFilterSelectionData(). If no filter
     *  has been selected by the user then the array will be empty.
     *
     * @return array
     */
    public function loadData($filter = [])
    {
        throw new Exception('getRequestArgs called but not implemented!');
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\controllers\grid\GridDataProvider', '\GridDataProvider');
}
