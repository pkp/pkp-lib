<?php
/**
 * @file classes/linkAction/request/AjaxAction.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AjaxAction
 * @ingroup linkAction_request
 *
 * @brief Class defining an AJAX action.
 */


define('AJAX_REQUEST_TYPE_GET', 'get');
define('AJAX_REQUEST_TYPE_POST', 'post');

import('lib.pkp.classes.linkAction.request.LinkActionRequest');

class AjaxAction extends LinkActionRequest {

	/** @var string */
	var $_remoteAction;

	/** @var string */
	var $_requestType;

	/** @var array */
	var $_requestData;

	/**
	 * Constructor
	 * @param $remoteAction string The target URL.
	 * @param $requestType string One of the AJAX_REQUEST_TYPE_* constants.
	 * @param $requestData array Any request data (e.g. POST params) to be sent.
	 */
	function __construct($remoteAction, $requestType = AJAX_REQUEST_TYPE_POST, $requestData = []) {
		parent::__construct();
		$this->_remoteAction = $remoteAction;
		$this->_requestType = $requestType;
		$this->_requestData = array_merge($requestData, [
			'csrfToken' => Application::get()->getRequest()->getSession()->getCSRFToken(),
		]);
	}


	//
	// Getters and Setters
	//
	/**
	 * Get the target URL.
	 * @return string
	 */
	function getRemoteAction() {
		return $this->_remoteAction;
	}

	/**
	 * Get the request type.
	 * @return string
	 */
	function getRequestType() {
		return $this->_requestType;
	}

	/**
	 * Get the request data.
	 * @return array
	 */
	function getRequestData() {
		return $this->_requestData;
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
			'url' => $this->getRemoteAction(),
			'requestType' => $this->getRequestType(),
			'data' => $this->getRequestData(),
		);
	}
}


