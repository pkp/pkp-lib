/**
 * @file js/classes/linkAction/PostAndRedirectRequest.js
 *
 * Copyright (c) 2000-2011 John Willinsky
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

		// We make use of a form to post and redirect at the same time.
		var $formElement = $('form', $linkActionElement);
		if (options.postData) {
			// We have data to post, so we prepare the form.
			var handleFormSubmit = $.pkp.classes.Helper.curry(
					this.handleFormSubmit_, this);
			$formElement.bind('submit', handleFormSubmit);
			$formElement.attr('action', options.url);
			$formElement.append('<input type="hidden" name="linkActionPostData" value="' +
					options.postData + '" />');

			this.$formElement_ = $formElement;
		} else {
			// We don't have data to post, remove the form element.
			$formElement.remove();
		}

		this.parent($linkActionElement, options);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.classes.linkAction.PostAndRedirectRequest,
			$.pkp.classes.linkAction.LinkActionRequest);


	//
	// Private properties
	//
	/**
	 * The form element used to post data and redirect.
	 * @private
	 * @type {Object}
	 */
	$.pkp.classes.linkAction.PostAndRedirectRequest.prototype.
			$formElement_ = null;


	//
	// Getters and Setters.
	//
	/**
	 * Return the form element.
	 * @return {Object} Form element.
	 */
	$.pkp.classes.linkAction.PostAndRedirectRequest.prototype.getFormElement =
			function() {
		return this.$formElement_;
	};


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

		// Check if we need to post any data or not.
		if (options.postData) {
			$.post(options.postUrl, { linkActionPostData: options.postData },
					responseHandler, 'json');
		} else {
			$.post(options.postUrl,	responseHandler, 'json');
		}

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

		var $formElement = this.getFormElement();
		if ($formElement === null) {
			// We don't have a form element, so we don't need to post any
			// data while redirecting.
			window.location = options.url;
		} else {
			// We have a form element, use it to redirect and post data
			// at the same time.
			$formElement.trigger('submit');
		}
	};


	/**
	 * The form submission handler.
	 * @private
	 * @param {Event} event The triggering event.
	 */
	$.pkp.classes.linkAction.PostAndRedirectRequest.prototype.handleFormSubmit_ =
			function(event) {
		// Avoid propagation of the form submit event.
		event.stopPropagation();
		this.finish();
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);
