/**
 * @file js/classes/linkAction/PostAndRedirectRequest.js
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PostAndRedirectRequest
 * @ingroup js_classes_linkAction
 *
 * @brief An action request that will post data and then redirect, using two
 * different urls. For both requests, it will post the passed data. If none is
 * passed, then it will post nothing. You can provide a js event response for
 * the first post request and it will be handled.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.linkAction.LinkActionRequest
	 *
	 * @param {jQuery} $linkActionElement The element the link
	 *  action was attached to.
	 * @param {Object} options Configuration of the link action
	 *  request.
	 */
	$.pkp.classes.linkAction.PostAndRedirectRequest =
			function($linkActionElement, options) {

		this.parent($linkActionElement, options);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.classes.linkAction.PostAndRedirectRequest,
			$.pkp.classes.linkAction.LinkActionRequest);


	//
	// Public methods
	//
	/**
	 * @inheritDoc
	 */
	$.pkp.classes.linkAction.PostAndRedirectRequest.prototype.activate =
			function(element, event) {
		var returner = this.parent('activate', element, event);
		var options = this.getOptions();

		// Create a response handler for the first request (post).
		var responseHandler = $.pkp.classes.Helper.curry(
				this.handleResponse_, this);

		// Post.
		$.post(options.postUrl, responseHandler, 'json');

		// We need to make sure that the finish() method will be called.
		// While the redirect request is running, user can click again
		// in the link (if it is still on page). If it happens, the link action
		// handler will run activate method again and this class will start the
		// post request. But when the redirect request finishes, it will stop
		// the post data request, and the responseHandler will never be called.
		// That's why we can't call the finish() method there.
		// So we use a timer to give some deactivated time to the link
		// to minimize double-execution (we can't avoid it totally because
		// we never know when the redirect request is over).
		var finishCallback = $.pkp.classes.Helper.curry(
				this.finishCallback_, this);
		setTimeout(finishCallback, 2000);

		return returner;
	};


	//
	// Private helper methods.
	//
	/**
	 * Callback to be called after a timeout.
	 * @private
	 */
	$.pkp.classes.linkAction.PostAndRedirectRequest.prototype.finishCallback_ =
			function() {
		this.finish();
	};


	/**
	 * The post data response handler.
	 * @param {Object} jsonData A parsed JSON response object.
	 * @private
	 */
	$.pkp.classes.linkAction.PostAndRedirectRequest.prototype.handleResponse_ =
			function(jsonData) {
		var options = this.getOptions();
		var $linkActionElement = this.getLinkActionElement();

		// Get the link action handler to handle the json response.
		var linkActionHandler = $.pkp.classes.Handler.getHandler($linkActionElement);
		linkActionHandler.handleJson(jsonData);

		// Redirect.
		window.location = options.url;
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);
