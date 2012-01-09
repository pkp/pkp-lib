<?php
/**
 * @file classes/linkAction/request/PostAndRedirectAction.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PostAndRedirectAction
 * @ingroup linkAction_request
 *
 * @brief This action request redirects to another page posting the passed data.
 */


import('lib.pkp.classes.linkAction.request.RedirectAction');

class PostAndRedirectAction extends RedirectAction {
	/** @var string The data to be posted by the link action form */
	var $_postData;

	/**
	 * Constructor
	 * @param $url string Target URL
	 * @param $postData string The data to be posted.
	 */
	function PostAndRedirectAction($url, $postData) {
		parent::RedirectAction($url);
		$this->_postData = $postData;
	}


	//
	// Getters and Setters
	//
	/**
	 * Get the post data.
	 * @return string
	 */
	function getPostData() {
		return $this->_postData;
	}


	//
	// Overridden protected methods from LinkActionRequest
	//
	/**
	 * @see LinkActionRequest::getJSLinkActionRequest()
	 */
	function getJSLinkActionRequest() {
		return '$.pkp.classes.linkAction.PostAndRedirectRequest';
	}

	/**
	 * @see LinkActionRequest::getLocalizedOptions()
	 */
	function getLocalizedOptions() {
		$options = parent::getLocalizedOptions();
		return array_merge($options,
			array('postData' => $this->getPostData())
		);
	}
}

?>
