<?php
/**
 * @file classes/linkAction/request/AjaxAction.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AjaxAction
 * @ingroup linkAction_request
 *
 * @brief Class defining an AJAX action.
 */


define('LINK_ACTION_TYPE_GET', 'get');
define('LINK_ACTION_TYPE_POST', 'post');

import('lib.pkp.classes.linkAction.request.LinkActionRequest');

class AjaxAction extends LinkActionRequest {
	/** @var string The LINK_ACTION_TYPE_(GET|POST) type of this action. */
	var $_type;

	/** @var string The URL this action will invoke */
	var $_url;

	/** @var string The ID of the DOM element to act on */
	var $_actOn;

	/**
	 * Constructor
	 * @param $type string LINK_ACTION_TYPE_...
	 * @param $url string Target URL
	 * @param $actOn string ID of element to act on
	 */
	function AjaxAction($type, $url, $actOn) {
		parent::LinkActionRequest();
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
	// Overridden protected methods from LinkActionRequest
	//
	/**
	 * @see LinkActionRequest::getJSLinkActionRequest()
	 */
	function getJSLinkActionRequest() {
		return '$.pkp.classes.linkAction.AjaxRequest';
	}

	/**
	 * @see LinkActionRequest::getLocalizedOptions()
	 */
	function getLocalizedOptions() {
		return array(
			'type' => $this->getType(),
			'url' => $this->getUrl(),
			'actOn' => $this->getActOn()
		);
	}
}

?>
