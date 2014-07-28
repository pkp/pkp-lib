/**
 * @file js/controllers/UrlInDivHandler.js
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UrlInDivHandler
 * @ingroup js_controllers
 *
 * @brief "URL in div" handler
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQueryObject} $divElement the wrapped div element.
	 * @param {Object} options options to be passed.
	 */
	$.pkp.controllers.UrlInDivHandler = function($divElement, options) {
		this.parent($divElement, options);

		// Store the URL (e.g. for reloads)
		this.sourceUrl_ = options.sourceUrl;

		// Load the contents.
		this.reload();
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.UrlInDivHandler, $.pkp.classes.Handler);


	//
	// Private properties
	//
	/**
	 * The URL to be used for data loaded into this div
	 * @private
	 * @type {?string}
	 */
	$.pkp.controllers.UrlInDivHandler.sourceUrl_ = null;


	//
	// Public Methods
	//
	/**
	 * Reload the div contents.
	 */
	$.pkp.controllers.UrlInDivHandler.prototype.reload = function() {
		$.get(this.sourceUrl_,
				this.callbackWrapper(this.handleLoadedContent_), 'json');
	};


	//
	// Private Methods
	//
	/**
	 * Handle a callback after a load operation returns.
	 *
	 * @param {Object} ajaxContext The AJAX request context.
	 * @param {Object} jsonData A parsed JSON response object.
	 * @return {boolean} Message handling result.
	 * @private
	 */
	/*jslint unparam: true*/
	$.pkp.controllers.UrlInDivHandler.prototype.handleLoadedContent_ =
			function(ajaxContext, jsonData) {

		var handledJsonData = this.handleJson(jsonData);
		if (handledJsonData.status === true) {
			this.getHtmlElement().hide().html(handledJsonData.content).fadeIn(400);
		} else {
			// Alert that loading failed.
			alert(handledJsonData.content);
		}

		return false;
	};
	/*jslint unparam: false*/

/** @param {jQuery} $ jQuery closure. */
}(jQuery));
