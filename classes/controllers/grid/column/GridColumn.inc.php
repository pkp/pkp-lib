<?php

/**
 * @file classes/controllers/grid/column/GridColumn.inc.php
 *
 * Copyright (c) 2000-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GridColumn
 * @ingroup controllers_grid_column
 *
 * @brief Represents a column within a grid. It is used to configure the way
 *  cells within a column are displayed (label provider) and can also be used
 *  to configure a editing strategy (not yet implemented). Contains all column-
 *  specific configuration (e.g. column title).
 *
 * FIXME: Implement editing strategy.
 */

// $Id$

class GridColumn {
	/** @var string the column id */
	var $_id;

	/** @var string the column title */
	var $_title;

	/** @var GridLabelProvider a label provider for cells in this column */
	var $_labelProvider;

	/**
	 * Constructor
	 */
	function GridColumn($id = '', $title = '', $labelProvider = null) {
		$this->_id = $id;
		$this->_title = $title;
		$this->_labelProvider =& $labelProvider;
	}

	//
	// Setters/Getters
	//
	/**
	 * Get the column id
	 * @return string
	 */
	function getId() {
		return $this->_id;
	}

	/**
	 * Set the column id
	 * @param $id string
	 */
	function setId($id) {
		$this->_id = $id;
	}


	/**
	 * Get the column title
	 * @return string
	 */
	function getTitle() {
		return $this->_title;
	}

	/**
	 * Set the column title
	 * @param $title string
	 */
	function setTitle($title) {
		$this->_title = $title;
	}

	/**
	 * Get the translated column title
	 * @return string
	 */
	function getLocalizedTitle() {
		return Locale::translate($this->_title);;
	}

	/**
	 * Get the label provider
	 * @return GridLabelProvider
	 */
	function &getLabelProvider() {
		if (is_null($this->_labelProvider)) {
			// provide a sensible default label provider
			import('controllers.grid.labelProvider.GridLabelProvider');
			$labelProvider =& new GridLabelProvider();
			$this->setLabelProvider($labelProvider);
		}
		return $this->_labelProvider;
	}

	/**
	 * Set the label provider
	 * @param $labelProvider GridLabelProvider
	 */
	function setLabelProvider(&$labelProvider) {
		$this->_labelProvider =& $labelProvider;
	}
}