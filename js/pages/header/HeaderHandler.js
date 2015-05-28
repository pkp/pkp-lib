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

		$('#notificationsToggle').click(this.callbackWrapper(
				this.appendToggleIndicator_));

		this.bind('updateUnreadNotificationsCount',
				this.fetchUnreadNotificationsCountHandler_);
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
	 * Toggle the notifications grid visibility
	 *
	 * @param {jQueryObject} callingElement The calling element.
	 *  that triggered the event.
	 * @param {Event} event The event.
	 * @private
	 */
	$.pkp.pages.header.HeaderHandler.prototype.appendToggleIndicator_ =
			function(callingElement, event) {

		var $header = this.getHtmlElement(),
				$popover = $header.find('#notificationsPopover'),
				$listElement = $header.find('li.notificationsLinkContainer'),
				$toggle = $header.find('#notificationsToggle');

		$popover.toggle();
		$listElement.toggleClass('expandedIndicator');
		$toggle.toggleClass('expandedIndicator');

		if ($listElement.hasClass('expandedIndicator')) {
			this.trigger('callWhenClickOutside', [{
				container: $popover.add($listElement),
				callback: this.callbackWrapper(this.appendToggleIndicator_),
				skipWhenVisibleModals: true
			}]);
		}
	};


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
	 * Handler to kick off a request to update the unread notifications
	 * count.
	 * @param {Object} ajaxContext The AJAX request context.
	 * @param {Object} jsonData A parsed JSON response object.
	 * @private
	 */
	$.pkp.pages.header.HeaderHandler.prototype.
			fetchUnreadNotificationsCountHandler_ = function(ajaxContext, jsonData) {

		$.get(this.options_.fetchUnreadNotificationsCountUrl,
				this.callbackWrapper(
				this.updateUnreadNotificationsCountHandler_), 'json');
	};


	/**
	 * Handler to update the unread notifications count upon receipt of
	 * an updated number.
	 * event.
	 * @param {Object} ajaxContext The AJAX request context.
	 * @param {Object} jsonData A parsed JSON response object.
	 * @private
	 */
	$.pkp.pages.header.HeaderHandler.prototype.
			updateUnreadNotificationsCountHandler_ = function(ajaxContext, jsonData) {

		this.getHtmlElement().find('#unreadNotificationCount').replaceWith(
				'<span id="unreadNotificationCount">' + jsonData.content + '</span>');
	};




/** @param {jQuery} $ jQuery closure. */
}(jQuery));
