/**
 * @defgroup js_classes_notification
 */
// Define the namespace
$.pkp.classes.notification = $.pkp.classes.notification || {};


/**
 * @file js/classes/notification/NotificationHelper.js
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NotificationHelper
 * @ingroup js_classes_notification
 *
 * @brief Class that perform notification helper actions.
 */
(function($) {

	$.pkp.classes.notification.NotificationHelper = function() {};

	//
	// Public static helper methods
	//
	/**
	 * Decides which notification will be used: in place or general.
	 * This method tries to find the closest in place notification element to the
	 * element that triggered the notiy user event. Only look upwards the DOM and stops
	 * searching when it reachs the handled element of the widget that is handling the
	 * notify user event. If it don't find any visible element, it bubbles up the event
	 * so the site handler can show general notifications.
	 *
	 * @param {$.pkp.classes.Handler} handler The widget handler that is handling the
	 * notify user event.
	 * @param {HTMLElement} triggerElement The element that triggered the notify
	 * user event.
	 */
	$.pkp.classes.notification.NotificationHelper.redirectNotifyUserEvent =
			function(handler, triggerElement) {

		// Get the selector for a notification element.
		$notificationSelector = '.pkp_notification';

		// Search for the closest in place notification element.
		var $thisElementParent = handler.getHtmlElement().parent();
		var $containerElement = $(triggerElement);
		var $notificationElement = $containerElement.children($notificationSelector);

		while ($notificationElement.length == 0 && $containerElement[0] != $thisElementParent[0]) {
			$containerElement = $containerElement.parent();
			$notificationElement = $containerElement.children($notificationSelector);
		}

		// Check if we found a notification element and if this element its
		// inside a visible parent.

		if ($notificationElement.length && $notificationElement.parent(':hidden').length == 0) {
			// Show in place notification to user.
			$notificationElement.triggerHandler('notifyUser');
		} else {
			// Bubble up the notify user event so the site can handle the
			// general notification.
			handler.getHtmlElement().parent().trigger('notifyUser');
		}
	};


	/** @param {jQuery} $ jQuery closure. */
})(jQuery);
