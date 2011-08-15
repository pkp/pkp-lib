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
		this.bind('notifyUser', this.notifyUserHandler_);
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


	//
	// Private methods.
	//
	/**
	 * Notify user handler. If we have any in place notification
	 * handler, trigger the notify user event there. Otherwise, bubbles
	 * up the notify user event.
	 * @param {HTMLElement} sourceElement The element that issued the
	 * "notifyUser" event.
	 * @param {Event} event The "notify user" event.
	 * @param {HTMLElement} triggerElement The element that triggered
	 * the "notifyUser" event.
	 * @private
	 */
	$.pkp.controllers.PageHandler.prototype.notifyUserHandler_ =
			function(sourceElement, event, triggerElement) {

		// Search for the closest in place notification element.
		var $thisElementParent = this.getHtmlElement().parent();
		var $containerElement = $(triggerElement);
		var $notificationElement = this.searchNotificationElement_($containerElement);

		while ($notificationElement.length == 0 && $containerElement[0] != $thisElementParent[0]) {
			$containerElement = $containerElement.parent();
			$notificationElement = this.searchNotificationElement_($containerElement);
		}

		// Check if we found a notification element.
		if ($notificationElement.length) {
			// Show in place notification to user.
			$notificationElement.triggerHandler('notifyUser');
		} else {
			// Didn´t find any in place notification element. Bubble up
			// the notify user event so the site can handle the
			// general notification.
			this.getHtmlElement().parent().trigger('notifyUser');
		}
	};

	/**
	 * Search for a notification element.
	 * @param {JQuery}$element The element that will be used
	 * in the search. Only the first level child elements will be
	 * considered.
	 * @returns {JQuery} or null
	 */
	$.pkp.controllers.PageHandler.prototype.searchNotificationElement_ =
			function($element) {

		var $notificationElement = $element.children('.pkp_notification');
		return $notificationElement;
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);
