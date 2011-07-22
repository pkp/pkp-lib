/**
 * @defgroup js_controllers
 */
// Create the controllers namespace.
jQuery.pkp.controllers = jQuery.pkp.controllers || { };

/**
 * @file js/controllers/SiteHandler.js
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SiteHandler
 * @ingroup js_controllers
 *
 * @brief Handle the site widget.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQuery} $widgetWrapper An HTML element that this handle will
	 * be attached to.
	 * @param {Object} options Handler options.
	 */
	$.pkp.controllers.SiteHandler = function($widgetWrapper, options) {
		this.parent($widgetWrapper, options);

		this.bind('redirectRequested', this.redirectToUrl);

		this.bind('notifyUser', this.fetchNotificationHandler_);

		this.options_ = options;

		// Check if we have notifications to show.
		if (options.hasSystemNotifications) {
			this.trigger('notifyUser');
		}
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.SiteHandler, $.pkp.classes.Handler);


	//
	// Private properties
	//
	/**
	 * Site handler options.
	 * @private
	 * @type {Object}
	 */
	$.pkp.controllers.SiteHandler.prototype.options_ = null;


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
	$.pkp.controllers.SiteHandler.prototype.redirectToUrl =
			function(sourceElement, event, url) {

		window.location = url;
	};


	//
	// Private methods
	//
	//
	// Private methods.
	//
	/**
	 * Fetch the notification data.
	 * @param {Object} element The active element.
	 * @param {Object} event The event that initiated this call.
	 */
	$.pkp.controllers.SiteHandler.prototype.fetchNotificationHandler_ =
			function() {

		$.get(this.options_.fetchNotificationUrl, this.options_.requestOptions,
				this.callbackWrapper(this.showNotificationResponseHandler_), 'json');
	};

	/**
	 * Response handler to the notification fetch.
	 *
	 * @param {content} jsonData A parsed JSON response object.
	 */
	$.pkp.controllers.SiteHandler.prototype.showNotificationResponseHandler_ =
			function(ajaxContext, jsonData) {
		var workingJsonData = this.handleJson(jsonData);

		if (workingJsonData !== false) {
			if (workingJsonData.content.general) {
				var dataInPlace = workingJsonData.content.general;
				var i, l;
				for (i = 0, l = dataInPlace.length; i < l; i++) {
					$.pnotify(dataInPlace[i]);
				}
			}
		}
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);
