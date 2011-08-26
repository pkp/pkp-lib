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

		// Hide the notification element.
		this.getHtmlElement().hide();

		// Trigger the notify user event without bubbling up.
		this.getHtmlElement().triggerHandler('notifyUser');
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

		var requestOptions = new Object();
		requestOptions.requestOptions = this.options_.requestOptions;

		// Avoid race conditions with other notification controllers.
		$.ajax({
			type: 'POST',
			url: this.options_.fetchNotificationUrl,
			data: requestOptions,
			success: this.callbackWrapper(this.showNotificationResponseHandler_),
			dataType: 'json',
			async: false
		});
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
				this.getHtmlElement().show();
			} else {
				this.getHtmlElement().empty();
				this.getHtmlElement().hide();
			}
		}
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);