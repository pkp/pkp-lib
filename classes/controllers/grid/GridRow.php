<?php

/**
 * @file classes/controllers/grid/GridRow.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GridRow
 *
 * @ingroup controllers_grid
 *
 * @brief GridRow implements a row of a Grid. See GridHandler for general
 *  information about grids.
 *
 * Each Grid is populated with data that is displayed in a series of rows. Each
 * row is implemented using a GridRow, which knows how to describe the data it
 * represents, and can present and manage row actions such as Edit and Delete
 * operations.
 *
 * For general information on grids, see GridHandler.
 */

namespace PKP\controllers\grid;

class GridRow extends GridBodyElement
{
    public const GRID_ACTION_POSITION_ROW_CLICK = 'row-click';
    public const GRID_ACTION_POSITION_ROW_LEFT = 'row-left';

    /** @var array */
    public $_requestArgs;

    /** @var string the grid this row belongs to */
    public $_gridId;

    /** @var mixed the row's data source */
    public $_data;

    /** @var bool true if the row has been modified */
    public $_isModified;

    /**
     * @var array row actions, the first key represents
     *  the position of the action in the row template,
     *  the second key represents the action id.
     */
    public $_actions = [GridHandler::GRID_ACTION_POSITION_DEFAULT => []];

    /** @var string the row template */
    public $_template;


    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->_isModified = false;
    }


    //
    // Getters/Setters
    //
    /**
     * Set the grid id
     *
     * @param string $gridId
     */
    public function setGridId($gridId)
    {
        $this->_gridId = $gridId;
    }

    /**
     * Get the grid id
     *
     * @return string
     */
    public function getGridId()
    {
        return $this->_gridId;
    }

    /**
     * Set the grid request parameters.
     *
     * @see GridHandler::getRequestArgs()
     *
     * @param array $requestArgs
     */
    public function setRequestArgs($requestArgs)
    {
        $this->_requestArgs = $requestArgs;
    }

    /**
     * Get the grid request parameters.
     *
     * @see GridHandler::getRequestArgs()
     *
     * @return array
     */
    public function getRequestArgs()
    {
        return $this->_requestArgs;
    }

    /**
     * Set the data element(s) for this controller
     *
     */
    public function setData(&$data)
    {
        $this->_data = & $data;
    }

    /**
     * Get the data element(s) for this controller
     */
    public function &getData()
    {
        return $this->_data;
    }

    /**
     * Set the modified flag for the row
     *
     * @param bool $isModified
     */
    public function setIsModified($isModified)
    {
        $this->_isModified = $isModified;
    }

    /**
     * Get the modified flag for the row
     *
     * @return bool
     */
    public function getIsModified()
    {
        return $this->_isModified;
    }

    /**
     * Get whether this row has any actions or not.
     *
     * @return bool
     */
    public function hasActions()
    {
        $allActions = [];
        foreach ($this->_actions as $actions) {
            $allActions = array_merge($allActions, $actions);
        }

        return !empty($allActions);
    }

    /**
     * Get all actions for a given position within the controller
     *
     * @param string $position the position of the actions
     *
     * @return array the LinkActions for the given position
     */
    public function getActions($position = GridHandler::GRID_ACTION_POSITION_DEFAULT)
    {
        if (!isset($this->_actions[$position])) {
            return [];
        }
        return $this->_actions[$position];
    }

    /**
     * Add an action
     *
     * @param mixed $action a single action
     * @param string $position the position of the action
     */
    public function addAction($action, $position = GridHandler::GRID_ACTION_POSITION_DEFAULT)
    {
        if (!isset($this->_actions[$position])) {
            $this->_actions[$position] = [];
        }
        $this->_actions[$position][$action->getId()] = $action;
    }

    /**
     * Get the row template - override base
     * implementation to provide a sensible default.
     *
     * @return string
     */
    public function getTemplate()
    {
        return $this->_template;
    }

    /**
     * Set the controller template
     *
     * @param string $template
     */
    public function setTemplate($template)
    {
        $this->_template = $template;
    }

    //
    // Public methods
    //
    /**
     * Initialize a row instance.
     *
     * Subclasses can override this method.
     *
     * @param PKPRequest $request
     * @param string $template
     */
    public function initialize($request, $template = null)
    {
        if ($template === null) {
            $template = 'controllers/grid/gridRow.tpl';
        }
        // Set the template.
        $this->setTemplate($template);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\controllers\grid\GridRow', '\GridRow');
    foreach ([
        'GRID_ACTION_POSITION_ROW_CLICK',
        'GRID_ACTION_POSITION_ROW_LEFT',
    ] as $constantName) {
        define($constantName, constant('\GridRow::' . $constantName));
    }
}
