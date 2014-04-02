<?php
/**
 * @file classes/linkAction/request/RedirectAction.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RedirectAction
 * @ingroup linkAction_request
 *
 * @brief This action request redirects to another page.
 */


import('lib.pkp.classes.linkAction.request.LinkActionRequest');

class RedirectAction extends LinkActionRequest {
	/** @var string The URL this action will invoke */
	var $_url;

	/**
	 * Constructor
	 * @param $url string Target URL
	 */
	function RedirectAction($url) {
		parent::LinkActionRequest();
		$this->_url = $url;
	}


	//
	// Getters and Setters
	//
	/**
	 * Get the target URL.
	 * @return string
	 */
	function getUrl() {
		return $this->_url;
	}


	//
	// Overridden protected methods from LinkActionRequest
	//
	/**
	 * @see LinkActionRequest::getJSLinkActionRequest()
	 */
	function getJSLinkActionRequest() {
		return '$.pkp.classes.linkAction.RedirectRequest';
	}

	/**
	 * @see LinkActionRequest::getLocalizedOptions()
	 */
	function getLocalizedOptions() {
		return array('url' => $this->getUrl());
	}
}

?>
