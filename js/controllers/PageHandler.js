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
	// Private properties.
	//
	/**
	 * The notification manager object.
	 * @private
	 * @type {$.pkp.classes.notification.InPlaceNotificationManager}
	 */
	$.pkp.controllers.PageHandler.prototype.notificationManager_ = null;

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
	 * Use notification manager object to fetch the notification data.
	 * @private
	 */
	$.pkp.controllers.PageHandler.prototype.notifyUserHandler_ =
			function(sourceElement, event, url) {
		var $notificationElement = $(".pkp_notification:visible");
		if ($notificationElement.length) {
			$notificationElement.triggerHandler('notifyUser');
		} else {
			this.getHtmlElement().parent().trigger('notifyUser');
		}
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);
