/**
 * @defgroup js_controllers
 */
// Create the controllers namespace.
jQuery.pkp.controllers = jQuery.pkp.controllers || { };

/**
 * @file js/controllers/SiteHandler.js
 *
 * Copyright (c) 2000-2012 John Willinsky
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

		// Initialize the navigation menu
		$('ul.sf-menu', $widgetWrapper).superfish();
		$('.go').button();

		this.bind('redirectRequested', this.redirectToUrl);

		this.bind('notifyUser', this.fetchNotificationHandler_);

		// Bind the pageUnloadHandler_ method to the DOM so it is
		// called.
		$(window).bind('beforeunload', this.callbackWrapper(this.pageUnloadHandler_));

		this.options_ = options;

		// Determine if the data changed message has been overridden
		// with an options element. If not, use the default provided by
		// the Application. Orignal Locale key: form.dataHasChanged.
		// @see PKPApplication::getJSLocaleKeys
		if (options.formDataChangedMessage) {
			this.formDataChangedMessage_ = options.formDataChangedMessage;
		} else {
			this.formDataChangedMessage_ = $.pkp.locale.form_dataHasChanged;
		}

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


	/**
	 * A state variable to determine if data has changed on the form.
	 * For 'cancel' and 'page unload' warnings.
	 * @private
	 * @type {Boolean}
	 */
	$.pkp.controllers.SiteHandler.prototype.formDataChanged_ = false;


	/**
	 * A state variable to store the message to display when the page is
	 * unloaded with unsaved data.
	 * @private
	 * @type {String}
	 */
	$.pkp.controllers.SiteHandler.prototype.formDataChangedMessage_ = null;


	/**
	 * A state variable to store the form elements that have unsaved data
	 * @private
	 * @type {Object}
	 */
	$.pkp.controllers.SiteHandler.prototype.unsavedFormElements_ = {};


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


	/**
	 * Method called by Form elements that wish to inform SiteHandler
	 * that they are in a changed/unsaved state.
	 *
	 * @param {HTMLElement} sourceElement The element wishes to
	 * register.
	 */
	$.pkp.controllers.SiteHandler.prototype.registerUnsavedFormElement =
			function(sourceElement) {

		var elementId = sourceElement.attr('id');
		this.unsavedFormElements_[elementId] = true;
	};


	/**
	 * Method called by Form elements that wish to inform SiteHandler
	 * that they no longer wish to be tracked as 'unsaved'.
	 *
	 * @param {HTMLElement} sourceElement The element that wishes to
	 * unregister.
	 */
	$.pkp.controllers.SiteHandler.prototype.unregisterUnsavedFormElement =
			function(sourceElement) {
		var elementId = sourceElement.attr('id');
		// this actually sets the property to undefined.
		// delete doesn't really delete.
		delete this.unsavedFormElements_[elementId];
	};


	//
	// Private methods.
	//
	/**
	 * Fetch the notification data.
	 * @param {HTMLElement} sourceElement The element that issued the
	 *  "fetchNotification" event.
	 * @param {Event} event The "fetch notification" event.
	 * @param {string?} jsonData The JSON content representing the
	 *  notification.
	 * @private
	 */
	$.pkp.controllers.SiteHandler.prototype.fetchNotificationHandler_ =
			function(sourceElement, event, jsonData) {

		if (jsonData !== undefined) {
			// This is an event that came from an inplace notification
			// widget that was not visible because of the scrolling.
			this.showNotification_(jsonData);
			return;
		}

		// Avoid race conditions with in place notifications.
		$.ajax({
			url: this.options_.fetchNotificationUrl,
			data: this.options_.requestOptions,
			success: this.callbackWrapper(this.showNotificationResponseHandler_),
			dataType: 'json',
			async: false
		});
	};


	/**
	 * Internal callback called upon page unload. If it returns
	 * anything other than void, a message will be displayed to
	 * the user.
	 *
	 * @private
	 *
	 * @param {Object} object The validator plug-in.
	 * @param {Event} event The wrapped HTML form.
	 * @return {string?} the warning message string, if needed.
	 */
	$.pkp.controllers.SiteHandler.prototype.pageUnloadHandler_ =
			function(object, event) {

		// any registered and then unregistered forms will exist
		// as properties in the unsavedFormElements_ object. They
		// will just be undefined.  See if there are any that are
		// not.

		var unsavedElementCount = 0;

		for (var element in this.unsavedFormElements_) {
			if (this.unsavedFormElements_[element] !== undefined) {
				unsavedElementCount++;
			}
		}
		if (unsavedElementCount > 0) {
			return this.formDataChangedMessage_;
		}
	};


	/**
	 * Response handler to the notification fetch.
	 *
	 * @param {Object} ajaxContext The data returned from the server.
	 * @param {content} jsonData A parsed JSON response object.
	 * @private
	 */
	$.pkp.controllers.SiteHandler.prototype.showNotificationResponseHandler_ =
			function(ajaxContext, jsonData) {
		this.showNotification_(jsonData);
	};


	//
	// Private helper method.
	//
	/**
	 * Show the notification content.
	 *
	 * @param {string} jsonData The JSON-encoded notification data.
	 * @private
	 */
	$.pkp.controllers.SiteHandler.prototype.showNotification_ =
			function(jsonData) {
		var workingJsonData = this.handleJson(jsonData);

		if (workingJsonData !== false) {
			if (workingJsonData.content.general) {
				var notificationsData = workingJsonData.content.general;
				for (var levelId in notificationsData) {
					for (var notificationId in notificationsData[levelId]) {
						$.pnotify(notificationsData[levelId][notificationId]);
					}
				}
			}
		}
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);
