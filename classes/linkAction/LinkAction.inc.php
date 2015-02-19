<?php
/**
 * @defgroup linkAction
 */

/**
 * @file classes/linkAction/LinkAction.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
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

	/** @var LinkActionRequest The action to be taken when the link action is activated */
	var $_actionRequest;

	/** @var string The localized title of the action. */
	var $_title;

	/** @var string The name of an icon for the action. */
	var $_image;

	/**
	 * Constructor
	 * @param $id string
	 * @param $actionRequest LinkActionRequest The action to be taken when the link action is activated.
	 * @param $title string (optional) The localized title of the action.
	 * @param $image string (optional) The name of an icon for the
	 *  action.
	 */
	function LinkAction($id, &$actionRequest, $title = null, $image = null) {
		$this->_id = $id;
		assert(is_a($actionRequest, 'LinkActionRequest'));
		$this->_actionRequest =& $actionRequest;
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
	 * Get the action handler.
	 * @return LinkActionRequest
	 */
	function &getActionRequest() {
		return $this->_actionRequest;
	}

	/**
	 * Get the localized action title.
	 * @return string
	 */
	function getTitle() {
		return $this->_title;
	}

	/**
	 * Get a title for display when a user hovers over the
	 * link action.  Default to the regular title if it is set.
	 * @return string
	 */
	function getHoverTitle() {
		// for the locale key, remove any unique ids from the id.
		$id = preg_replace('/([^-]+)\-.+$/', '$1', $this->getId());
		$title = __('grid.action.' . $id);
		return $title;
	}

	/**
	 * Get the action image.
	 * @return string
	 */
	function getImage() {
		return $this->_image;
	}
}

?>
