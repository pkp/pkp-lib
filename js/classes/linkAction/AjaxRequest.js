/**
 * @file js/classes/linkAction/AjaxRequest.js
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AjaxRequest
 * @ingroup js_classes_linkAction
 *
 * @brief AJAX link action request.
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
	$.pkp.classes.linkAction.AjaxRequest =
			function($linkActionElement, options) {

		this.parent($linkActionElement, options);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.classes.linkAction.AjaxRequest,
			$.pkp.classes.linkAction.LinkActionRequest);


	//
	// Public methods
	//
	/**
	 * @inheritDoc
	 */
	$.pkp.classes.linkAction.AjaxRequest.prototype.activate =
			function(element, event) {

		var returnValue = this.parent('activate', element, event),
				options = this.getOptions();

		var responseHandler = $.pkp.classes.Helper.curry(
				this.handleResponse, this);
		switch (options.requestType) {
			case 'get':
				$.getJSON(options.url,
						responseHandler);
				break;

			case 'post':
				$.post(options.url,
						responseHandler,
						'json');
				break;
		}
		return returnValue;
	};


	/**
	 * Handle the AJAX response.
	 * @param {Object} jsonData The data returned by the server.
	 */
	$.pkp.classes.linkAction.AjaxRequest.prototype.handleResponse =
			function(jsonData) {

		var $linkActionHandler = this.getLinkActionElement().data('pkp.handler');
		$linkActionHandler.handleJson(jsonData);
		this.finish();
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);
