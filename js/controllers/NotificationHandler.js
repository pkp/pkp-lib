/**
 * @file js/controllers/NotificationHandler.js
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NotificationHandler
 * @ingroup js_controllers
 *
 * @brief Handle in place notifications.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQuery} $notification The html notification element.
	 * @param {Object} options Notification options.
	 */
	$.pkp.controllers.NotificationHandler =
			function($notificationElement, options) {
		this.parent($notificationElement, options);

		this.options_ = options;

		this.bind('notifyUser', this.fetchNotificationHandler_);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.NotificationHandler,
			$.pkp.classes.Handler);


	//
	// Private properties.
	//
	/**
	 * The options to fetch a notification.
	 * @private
	 * @type {Object}
	 */
	$.pkp.controllers.NotificationHandler.prototype.options_ = null;


	//
	// Private methods.
	//
	/**
	 * Handler to fetch the notification data.
	 */
	$.pkp.controllers.NotificationHandler.prototype.fetchNotificationHandler_ =
			function() {

		$.get(this.options_.fetchNotificationUrl, null,
				this.callbackWrapper(this.showNotificationResponseHandler_), 'json');
	};

	/**
	 * Callback to show the notification data in place.
	 *
	 * @param {Object} ajaxContext The AJAX request context.
	 * @param {Object} jsonData A parsed JSON response object.
	 */
	$.pkp.controllers.NotificationHandler.prototype.showNotificationResponseHandler_ =
			function(ajaxContext, jsonData) {
		var workingJsonData = this.handleJson(jsonData);

		if (workingJsonData !== false) {
			if (workingJsonData.content.inPlace) {
				var dataInPlace = workingJsonData.content.inPlace;
				this.getHtmlElement().html(dataInPlace);
			}
		}
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);