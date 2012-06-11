/**
 * @file js/controllers/NotificationHandler.js
 *
 * Copyright (c) 2000-2012 John Willinsky
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


	/**
	 * Time to hide trivial inplace notifications.
	 * @private
	 * @type {Object}
	 */
	$.pkp.controllers.NotificationHandler.prototype.trivialTimer_ = null;


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
		// Delete any existing trivial notification timer.
		clearTimeout(this.trivialTimer_);

		var $notificationElement = this.getHtmlElement();
		var workingJsonData = this.handleJson(jsonData);

		if (workingJsonData === false) {
			return;
		}
		if (workingJsonData.content.inPlace) {
			var inPlaceNotificationsData = this.setupNotifications_(
					workingJsonData.content.inPlace);
			var newNotificationsData = this.removeAlreadyShownNotifications_(
					workingJsonData);

			$notificationElement.html(inPlaceNotificationsData);
			$notificationElement.show();

			if (!(this.visibleWithoutScrolling_()) && newNotificationsData) {
				$notificationElement.parent().trigger('notifyUser', newNotificationsData);
			}
		} else {
			this.getHtmlElement().empty();
			this.getHtmlElement().hide();
		}
	};


	/**
	 * Check if the notification is inside the window are visible.
	 * @return {boolean} True iff the notification is fully visible.
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
		var $parentModalContentWrapper = $notificationElement
				.parents('.ui-dialog-content');
		if ($parentModalContentWrapper.length > 0) {
			var modalContentTop = $parentModalContentWrapper.offset().top;
			var modalContentBottom = modalContentTop +
					$parentModalContentWrapper.height();
			if (notificationMiddle < modalContentTop ||
					notificationMiddle > modalContentBottom) {
				// The element is outside of the modal content wrapper area.
				return false;
			}
		}

		// Check if the element is inside of the visible window are.
		if (notificationMiddle < windowScrollTop ||
				notificationMiddle > windowBottom) {
			return false;
		} else {
			return true;
		}
	};


	/**
	 * Remove notification data from object that is already on page.
	 * @param {Object} notificationsData The notification data to perform
	 *  the deletion on.
	 * @return {Object} Notification data after deletion.
	 * @private
	 */
	$.pkp.controllers.NotificationHandler.prototype.
			removeAlreadyShownNotifications_ = function(notificationsData) {

		var workingNotificationsData = notificationsData;
		var emptyObject = true;
		for (var levelId in workingNotificationsData.content.inPlace) {
			for (var notificationId in
					workingNotificationsData.content.inPlace[levelId]) {
				var element = $('#pkp_notification_' + notificationId);
				if (element.length > 0) {
					delete workingNotificationsData.content.
							inPlace[levelId][notificationId];
					delete workingNotificationsData.content.
							general[levelId][notificationId];
				} else {
					emptyObject = false;
				}
			}
		}
		if (emptyObject) {
			return false;
		} else {
			return workingNotificationsData;
		}
	};


	/**
	 * Concatenate notification data in a string variable and
	 * add a timer to trivial notifications to make them dissapear.
	 * @param {Object} notificationsData The notification data to assemble
	 *  the concatenation from.
	 * @return {string} The concatenated notification data.
	 * @private
	 */
	$.pkp.controllers.NotificationHandler.prototype.
			setupNotifications_ = function(notificationsData) {
		var returner = '';
		var trivialNotifications = new Array();
		for (var levelId in notificationsData) {
			for (var notificationId in notificationsData[levelId]) {
				// Store all trivial notification ids.
				if (levelId == 1) { // Trivial level.
					trivialNotifications.push(notificationId);
				}
				// Concatenate all notifications.
				returner += notificationsData[levelId][notificationId];
			}
		}

		if (trivialNotifications.length) {
			this.trivialTimer_ = setTimeout(function() {
				for (var notificationId in trivialNotifications) {
					var $notification = $('#pkp_notification_' +
							trivialNotifications[notificationId]);
					$notification.fadeOut(400, function() {
						$(this).remove();
					});
				}
			}, 6000);
		}

		return returner;
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);
