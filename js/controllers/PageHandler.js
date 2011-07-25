/**
 * @file js/controllers/PageHandler.js
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PageHandler
 * @ingroup js_controllers
 *
 * @brief Handle the page widget.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQuery} $page the wrapped page element.
	 * @param {Object} options handler options.
	 */
	$.pkp.controllers.PageHandler = function($page, options) {
		this.parent($page, options);

		this.bind('redirectRequested', this.redirectToUrl);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.PageHandler, $.pkp.classes.Handler);


	//
	// Public methods
	//
	/**
	 * Callback that is triggered when the page should redirect.
	 *
	 * @param {HTMLElement} sourceElement The element that issued the
	 *  "redirectRequested" event.
	 * @param {Event} event The "redirect requested" event.
	 * @param {string} url The URL to redirect to.
	 */
	$.pkp.controllers.PageHandler.prototype.redirectToUrl =
			function(sourceElement, event, url) {

		window.location = url;
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);
