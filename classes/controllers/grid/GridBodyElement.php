<?php

/**
 * @file classes/controllers/grid/GridBodyElement.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GridBodyElement
 *
 * @ingroup controllers_grid
 *
 * @brief Base class for grid body elements.
 */

namespace PKP\controllers\grid;

class GridBodyElement
{
    /**
     * @var string identifier of the element instance - must be unique
     *  among all instances within a grid.
     */
    public $_id;

    /**
     * @var array flags that can be set by the handler to trigger layout
     *  options in the element or in cells inside of it.
     */
    public $_flags;

    /** @var GridCellProvider a cell provider for cells inside this element */
    public $_cellProvider;

    /**
     * Constructor
     *
     * @param null|mixed $cellProvider
     */
    public function __construct($id = '', $cellProvider = null, $flags = [])
    {
        $this->_id = $id;
        $this->_cellProvider = $cellProvider;
        $this->_flags = $flags;
    }

    //
    // Setters/Getters
    //
    /**
     * Get the element id
     *
     * @return string
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * Set the element id
     *
     * @param string $id
     */
    public function setId($id)
    {
        $this->_id = $id;
    }

    /**
     * Get all layout flags
     *
     * @return array
     */
    public function getFlags()
    {
        return $this->_flags;
    }

    /**
     * Get a single layout flag
     *
     * @param string $flag
     */
    public function getFlag($flag)
    {
        assert(isset($this->_flags[$flag]));
        return $this->_flags[$flag];
    }

    /**
     * Check whether a layout flag is set to true.
     *
     * @param string $flag
     *
     * @return bool
     */
    public function hasFlag($flag)
    {
        if (!isset($this->_flags[$flag])) {
            return false;
        }
        return (bool)$this->_flags[$flag];
    }

    /**
     * Add a layout flag
     *
     * @param string $flag
     * @param mixed $value optional
     */
    public function addFlag($flag, $value = true)
    {
        $this->_flags[$flag] = $value;
    }

    /**
     * Get the cell provider
     *
     * @return GridCellProvider
     */
    public function getCellProvider()
    {
        return $this->_cellProvider;
    }

    /**
     * Set the cell provider
     *
     * @param GridCellProvider $cellProvider
     */
    public function setCellProvider($cellProvider)
    {
        $this->_cellProvider = $cellProvider;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\controllers\grid\GridBodyElement', '\GridBodyElement');
}
