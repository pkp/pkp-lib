<?php
/**
 * @defgroup linkAction
 */

/**
 * @file classes/linkAction/LinkAction.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class LinkAction
 * @ingroup linkAction
 *
 * @brief Base class defining an action that can be performed by the user
 *  in the user interface.
 */

class LinkAction {
	/** @var string the id of the action */
	var $_id;

	/** @var string A translation key defining the title of the action. */
	var $_title;

	/** @var string The name of an icon for the action. */
	var $_image;

	/**
	 * Constructor
	 * @param $id string
	 * @param $title string (optional) A translation key defining
	 *  the title of the action.
	 * @param $image string (optional) The name of an icon for the
	 *  action.
	 */
	function LinkAction($id, $title = null, $image = null) {
		$this->_id = $id;
		$this->_title = $title;
		$this->_image = $image;
	}


	//
	// Getters and Setters
	//
	/**
	 * Get the action id.
	 * @return string
	 */
	function getId() {
		return $this->_id;
	}

	/**
	 * Get the action title.
	 * @return string
	 */
	function getTitle() {
		return $this->_title;
	}

	/**
	 * Get the action image.
	 * @return string
	 */
	function getImage() {
		return $this->_image;
	}


	//
	// Protected methods
	//
	/**
	 * Get the template responsible for
	 * rendering this link action.
	 * @return string
	 */
	function getTemplate() {
		return 'linkAction/linkAction.tpl';
	}
}

?>
