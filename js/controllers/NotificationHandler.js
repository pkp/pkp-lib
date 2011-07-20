/**
 * @file js/controllers/NotificationHandler.js
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NotificationHandler
 * @ingroup js_controllers
 *
 * @brief A basic handler for the general user notification widget.
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
	$.pkp.controllers.NotificationHandler = function($widgetWrapper, options) {
		this.parent($widgetWrapper, options);

		this.fetchNotificationUrl_ = options.fetchNotificationUrl;

		this.bind('notifyUser', this.fetchNotificationHandler_);

		// Check if we have notifications to show.
		if (options.hasSystemNotifications) {
			this.trigger('notifyUser');
		}
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.NotificationHandler, $.pkp.classes.Handler);


	//
	// Private properties
	//
	/**
	 * The URL to fetch notification data.
	 * @private
	 * @type {array}
	 */
	$.pkp.controllers.NotificationHandler.prototype.fetchNotificationUrl_ = null;


	//
	// Private methods
	//
	/**
	 * Event handler to refresh the notifications data.
	 *
	 * @param {Array} notification Notification data to be shown to user.
	 * @private
	 */
	$.pkp.controllers.NotificationHandler.prototype.showNotification_ =
			function(notification) {

		// This code should be adapted if we don't keep
		// using pnotify as our general user notification system.
		var i, l;
		for (i = 0, l = notification.length; i < l; i++) {
			$.pnotify(notification[i]);
		}
	};


	/**
	 * Fetch the notifications data from server.
	 * @private
	 */
	$.pkp.controllers.NotificationHandler.prototype.fetchNotificationHandler_ =
			function() {

		$.get(this.fetchNotificationUrl_, null,
				this.callbackWrapper(this.showNotificationsResponseHandler_), 'json');
	};


	/**
	 * Callback to show notifications.
	 *
	 * @param {Object} ajaxContext The AJAX request context.
	 * @param {Object} jsonData A parsed JSON response object.
	 * @private
	 */
	$.pkp.controllers.NotificationHandler.prototype.showNotificationsResponseHandler_ =
			function(ajaxContext, jsonData) {

		jsonData = this.handleJson(jsonData);
		if (jsonData !== false) {
			this.showNotification_(jsonData.content);
		}
	};
/** @param {jQuery} $ jQuery closure. */
})(jQuery);
