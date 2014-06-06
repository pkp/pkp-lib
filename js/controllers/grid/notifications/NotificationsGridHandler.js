/**
 * @file js/controllers/grid/notifications/NotificationsGridHandler.js
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NotificationsGridHandler
 * @ingroup js_controllers_grid
 *
 * @brief Category grid handler.
 */
(function($) {

	// Define the namespace.
	$.pkp.controllers.grid.notifications = $.pkp.controllers.grid.notifications || {};
	


	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.grid.GridHandler
	 *
	 * @param {jQueryObject} $grid The grid this handler is
	 *  attached to.
	 * @param {Object} options Grid handler configuration.
	 */
	$.pkp.controllers.grid.notifications.NotificationsGridHandler = function($grid, options) {
		$grid.find('a[id*="markNew"]').mousedown(
				this.callbackWrapper(this.markNewHandler_));

		$grid.find('a[id*="markRead"]').mousedown(
				this.callbackWrapper(this.markReadHandler_));

		$grid.find('a[id*="deleteNotifications"]').mousedown(
				this.callbackWrapper(this.deleteHandler_));

		this.parent($grid, options);
	};
	$.pkp.classes.Helper.inherits($.pkp.controllers.grid.notifications.NotificationsGridHandler,
			$.pkp.controllers.grid.GridHandler);


	//
	// Protected properties
	//
	/**
	 * The "mark notifications as new" URL
	 * @protected
	 * @type {?string}
	 */
	$.pkp.controllers.grid.notifications.NotificationsGridHandler.prototype.markNewUrl_ = null;


	/**
	 * The "mark notifications as read" URL
	 * @protected
	 * @type {?string}
	 */
	$.pkp.controllers.grid.notifications.NotificationsGridHandler.prototype.markReadUrl_ = null;


	/**
	 * The "delete notifications" URL
	 * @protected
	 * @type {?string}
	 */
	$.pkp.controllers.grid.notifications.NotificationsGridHandler.prototype.deleteUrl_ = null;


	//
	// Extended methods from GridHandler
	//
	/**
	 * @inheritDoc
	 */
	$.pkp.controllers.grid.notifications.NotificationsGridHandler.prototype.initialize =
			function(options) {

		// Save the URLs to interact with selected sets of notifications
		this.markNewUrl_ = options.markNewUrl;
		this.markReadUrl_ = options.markReadUrl;
		this.deleteUrl_ = options.deleteUrl;

		this.parent('initialize', options);
	};


	//
	// Private methods.
	//
	/**
	 * Callback that will be activated when the "mark new" icon is clicked
	 *
	 * @private
	 *
	 * @param {Object} callingContext The calling element or object.
	 * @param {Event=} opt_event The triggering event (e.g. a click on
	 *  a button.
	 * @return {boolean} Should return false to stop event processing.
	 */
	$.pkp.controllers.grid.notifications.NotificationsGridHandler.prototype.markNewHandler_ =
			function(callingContext, opt_event) {

		var selectedElements = [];
		this.getHtmlElement().find('input:checkbox:checked').each(function() 
{
			selectedElements.push($(this).val());
		});

		$.post(this.markNewUrl_, {selectedElements: selectedElements}, this.callbackWrapper(this.responseHandler_, null), 'json');

		return false;
	};


	/**
	 * Callback that will be activated when the "mark read" icon is clicked
	 *
	 * @private
	 *
	 * @param {Object} callingContext The calling element or object.
	 * @param {Event=} opt_event The triggering event (e.g. a click on
	 *  a button.
	 * @return {boolean} Should return false to stop event processing.
	 */
	$.pkp.controllers.grid.notifications.NotificationsGridHandler.prototype.markReadHandler_ =
			function(callingContext, opt_event) {

		var selectedElements = [];
		this.getHtmlElement().find('input:checkbox:checked').each(function() 
{
			selectedElements.push($(this).val());
		});

		$.post(this.markReadUrl_, {selectedElements: selectedElements}, this.callbackWrapper(this.responseHandler_, null), 'json');

		return false;
	};


	/**
	 * Callback that will be activated when the "delete" icon is clicked
	 *
	 * @private
	 *
	 * @param {Object} callingContext The calling element or object.
	 * @param {Event=} opt_event The triggering event (e.g. a click on
	 *  a button.
	 * @return {boolean} Should return false to stop event processing.
	 */
	$.pkp.controllers.grid.notifications.NotificationsGridHandler.prototype.deleteHandler_ =
			function(callingContext, opt_event) {

		var selectedElements = [];
		this.getHtmlElement().find('input:checkbox:checked').each(function() 
{
			selectedElements.push($(this).val());
		});

		$.post(this.deleteUrl_, {selectedElements: selectedElements}, this.callbackWrapper(this.responseHandler_, null), 'json');

		return false;
	};


	/**
	 * Callback after a response returns from the server.
	 *
	 * @private
	 *
	 * @param {Object} ajaxContext The AJAX request context.
	 * @param {Object} jsonData A parsed JSON response object.
	 */
	$.pkp.controllers.grid.notifications.NotificationsGridHandler.prototype.
			responseHandler_ = function(ajaxContext, jsonData) {

		this.handleJson(jsonData);
	};
/** @param {jQuery} $ jQuery closure. */
}(jQuery));
