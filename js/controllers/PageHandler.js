
/**
 * @file js/controllers/PageHandler.js
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PageHandler
 * @ingroup js_controllers
 *
 * @brief PKP handler for the page level (bound typically to the div.main element).
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQuery} $page the wrapped HTML form element.
	 * @param {Object} options options to be passed
	 *  into the validator plug-in.
	 */
	$.pkp.controllers.PageHandler = function($page, options) {
		this.parent($page, options);

		// Check whether we really got a form.
		if (!$page.is('div')) {
			throw Error(['A PageHandler controller can only be bound',
				' to a div element!'].join(''));
		}

		this.bind('redirectRequested', this.redirectToUrl);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.PageHandler, $.pkp.classes.Handler);


	//
	// Public methods
	//
	/**
	 * Callback that is triggered when the page should redirect
	 * after a modal form submitted.
	 *
	 * @param {HTMLElement} sourceElement The element that issued the
	 *  "redirectRequested" event.
	 * @param {Event} event The "element deleted" event.
	 * @param string The URL to redirect to
	 */
	$.pkp.controllers.PageHandler.prototype.redirectToUrl =
		function(sourceElement, event, url) {

		window.location = url;
	};



/** @param {jQuery} $ jQuery closure. */
})(jQuery);
