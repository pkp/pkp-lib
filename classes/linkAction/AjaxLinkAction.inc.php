<?php
/**
 * @file classes/linkAction/AjaxLinkAction.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AjaxLinkAction
 * @ingroup linkAction
 *
 * @brief Class defining an AJAX action.
 */

define('LINK_ACTION_TYPE_GET', 'get');
define('LINK_ACTION_TYPE_POST', 'post');

import('lib.pkp.classes.linkAction.LinkAction');

class AjaxLinkAction extends LinkAction {
	/** @var string The LINK_ACTION_TYPE_(GET|POST) type of this action. */
	var $_type;

	/** @var string The URL this action will invoke */
	var $_url;

	/** @var string The ID of the DOM element to act on */
	var $_actOn;

	/**
	 * Constructor
	 * @param $id string
	 * @param $type string LINK_ACTION_TYPE_...
	 * @param $url string Target URL
	 * @param $actOn string ID of element to act on
	 * @param $title string (optional)
	 * @param $image string (optional)
	 */
	function AjaxLinkAction($id, $type, $url, $actOn, $title = null, $image = null) {
		parent::LinkAction($id, $title, $image);
		$this->_type = $type;
		$this->_url = $url;
		$this->_actOn = $actOn;
	}


	//
	// Getters and Setters
	//
	/**
	 * Get the modal object.
	 * @return Modal
	 */
	function getType() {
		return $this->_type;
	}

	/**
	 * Get the target URL.
	 * @return string
	 */
	function getUrl() {
		return $this->_url;
	}

	/**
	 * Get ID of the element to act on
	 * @return string
	 */
	function getActOn() {
		return $this->_actOn;
	}

	//
	// Overridden protected methods from LinkAction
	//
	/**
	 * @see LinkAction::getTemplate()
	 */
	function getTemplate() {
		return 'linkAction/ajaxLinkAction.tpl';
	}
}

?>
