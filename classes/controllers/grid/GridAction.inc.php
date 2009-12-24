<?php


/**
 * @file classes/controllers/grid/action/GridAction.inc.php
 *
 * Copyright (c) 2000-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GridAction
 * @ingroup controllers_grid_action
 *
 * @brief Base class defining an action that can be performed within a Grid
 */

define('GRID_ACTION_MODE_MODAL', 1);
define('GRID_ACTION_MODE_LINK', 2);
define('GRID_ACTION_MODE_AJAX', 3);
define('GRID_ACTION_MODE_CONFIRM', 4);

define('GRID_ACTION_TYPE_NOTHING', '');
define('GRID_ACTION_TYPE_APPEND', 'append');
define('GRID_ACTION_TYPE_REPLACE', 'replace');
define('GRID_ACTION_TYPE_REMOVE', 'remove');

class GridAction {
	/** @var string the id of the action */
	var $_id;

	/** @var string url of the action */
	var $_url;

	/** @var integer the mode of the action (modal, ajax, link, etc) */
	var $_mode;

	/** @var string the type of action to be done on callback */
	var $_type;

	/** @var string optional, the title of the link, translated */
	var $_title;

	/** @var string optional, the URL to the image to be linked to */
	var $_image;

	function GridAction($id, $mode, $type, $url, $title = null, $image = null) {
		$this->_id = $id;
		$this->_mode = $mode;
		$this->_type = $type;
		$this->_url = $url;
		$this->_title = $title;
		$this->_image = $image;
	}

	function setId($id) {
		$this->_id = $id;
	}

	function getId() {
		return $this->_id;
	}

	function setMode($mode) {
		$this->_mode = $mode;
	}

	function getMode() {
		return $this->_mode;
	}

	function setType($type) {
		$this->_type = $type;
	}

	function getType() {
		return $this->_type;
	}

	function setUrl($url) {
		$this->_url = $url;
	}

	function getUrl() {
		return $this->_url;
	}

	function setTitle($title) {
		$this->_title = $title;
	}

	function getTitle() {
		return $this->_title;
	}

	function setImage($image) {
		$this->_image = $image;
	}

	function getImage() {
		return $this->_image;
	}
}

?>
