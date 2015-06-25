/**
 * @file js/pages/header/HeaderHandler.js
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class HeaderHandler
 * @ingroup js_pages_index
 *
 * @brief Handler for the site header.
 *
 */
(function($) {

	/** @type {Object} */
	$.pkp.pages = $.pkp.pages || { header: { } };

	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQueryObject} $headerElement The HTML element encapsulating
	 *  the header.
	 * @param {{requestedPage: string,
	 *  fetchUnreadNotificationsCountUrl: string}} options Handler options.
	 */
	$.pkp.pages.header.HeaderHandler =
			function($headerElement, options) {

		this.options_ = options;
		this.parent($headerElement, options);

		// Bind to the link action for toggling inline help.
		$headerElement.find('[id^="toggleHelp"]').click(
				this.callbackWrapper(this.toggleInlineHelpHandler_));
		this.publishEvent('toggleInlineHelp');
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.pages.header.HeaderHandler,
			$.pkp.classes.Handler);


	/**
	 * Site handler options.
	 * @private
	 * @type {{requestedPage: string}?}
	 */
	$.pkp.pages.header.HeaderHandler.prototype.options_ = null;


	//
	// Private helper methods
	//
	/**
	 * Respond to a user toggling the display of inline help.
	 *
	 * @param {HTMLElement} sourceElement The element that
	 *  issued the event.
	 * @param {Event} event The triggering event.
	 * @return {boolean} Always returns false.
	 * @private
	 */
	$.pkp.pages.header.HeaderHandler.prototype.toggleInlineHelpHandler_ =
			function(sourceElement, event) {
		this.trigger('toggleInlineHelp');
		return false;
	};




/** @param {jQuery} $ jQuery closure. */
}(jQuery));
