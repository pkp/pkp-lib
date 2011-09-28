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
	 * @param {jQuery} $notificationElement The html notification element.
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
	 * @private
	 */
	$.pkp.controllers.NotificationHandler.prototype.fetchNotificationHandler_ =
			function() {

		var requestOptions = {};
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
	 * @private
	 */
	$.pkp.controllers.NotificationHandler.prototype.
			showNotificationResponseHandler_ = function(ajaxContext, jsonData) {
		var workingJsonData = this.handleJson(jsonData);

		if (workingJsonData !== false) {
			if (workingJsonData.content.inPlace) {
				var dataInPlace = workingJsonData.content.inPlace;
				this.getHtmlElement().html(dataInPlace);
				this.getHtmlElement().show();

				if (!this.visibleWithoutScrolling_()) {
					this.getHtmlElement().parent().trigger('notifyUser', jsonData);
				}
			} else {
				this.getHtmlElement().empty();
				this.getHtmlElement().hide();
			}
		}
	};

	/**
	 * Check if the notification is inside the window visible are.
	 * @return {boolean}
	 * @private
	 */
	$.pkp.controllers.NotificationHandler.prototype.
			visibleWithoutScrolling_ = function() {
		var $notificationElement = this.getHtmlElement();
		var notificationTop = $notificationElement.offset().top;
		var notificationMiddle = notificationTop + this.getHtmlElement().height() / 2;

		var windowScrollTop = $(window).scrollTop();
		var windowBottom = windowScrollTop + $(window).height();

		// Consider modals and its own scroll functionality.
		$parentModalContentWrapper = $notificationElement.parents('.ui-dialog-content');
		if ($parentModalContentWrapper.length > 0) {
			var modalContentTop = $parentModalContentWrapper.offset().top;
			var modalContentBottom = modalContentTop + $parentModalContentWrapper.height();
			if (notificationMiddle < modalContentTop || notificationMiddle > modalContentBottom) {
				// The element is outside of the modal content wrapper area.
				return false;
			}
		}

		// Check if the element is inside of the visible window are.
		if (notificationMiddle < windowScrollTop || notificationMiddle > windowBottom) {
			return false;
		} else {
			return true;
		}
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);
