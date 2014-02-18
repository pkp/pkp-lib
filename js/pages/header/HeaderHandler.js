/**
 * @file js/pages/header/HeaderHandler.js
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class HeaderHandler
 * @ingroup js_pages_index
 *
 * @brief Handler for the site header.
 *
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQueryObject} $headerElement The HTML element encapsulating
	 *  the header.
	 * @param {{requestedPage: string}} options Handler options.
	 */
	$.pkp.pages.header.HeaderHandler =
			function($headerElement, options) {

		this.options_ = options;
		this.parent($headerElement, options);

		this.initializeMenu_();

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
	// Private helper methods.
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


	/**
	 * Initialize navigation menu.
	 * @private
	 */
	$.pkp.pages.header.HeaderHandler.prototype.initializeMenu_ =
			function() {
		var $header = this.getHtmlElement(),
				$menu = $('ul.sf-menu', $header),
				requestedPage = this.options_.requestedPage,
				currentUrl = window.location.href,
				$linkInMenu = $('a[href="' + currentUrl + '"]', $menu).
						parentsUntil('ul.sf-menu').last();
		$menu.superfish();

		if ($linkInMenu.length === 0 && requestedPage !== '') {
			// Search for the current url inside the menu links. If not present,
			// remove part of the url and try again until we've removed the
			// page handler part.
			while (true) {
				// Make the url less specific.
				currentUrl = currentUrl.substr(0, currentUrl.lastIndexOf('/'));

				// Make sure we still have the page handler part in url.
				if (currentUrl.indexOf(requestedPage) === -1) {
					break;
				}

				$linkInMenu = $linkInMenu.add($('a[href="' + currentUrl + '"]',
						$menu).parentsUntil('ul.sf-menu').last());
			}
		}

		if ($linkInMenu.length === 1) {
			// Add the current page style.
			$('a', $linkInMenu).first().addClass('pkp_helpers_underline');
			// } else {
			// There is no element or more than one that can represent
			// the current page. For now we don't have a use case for this,
			// can be extended if needed.
		}
	};


/** @param {jQuery} $ jQuery closure. */
}(jQuery));
