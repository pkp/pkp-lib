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
		this.bind('notifyUser', this.redirectNotifyUserEventHandler_);

		// Listen to this event to be able to redirect to the
		// correpondent grid a dataChanged event that comes from
		// a link action that is outside of any grid.
		this.bind('dataChanged', this.redirectDataChangedEventHandler_);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.PageHandler, $.pkp.classes.Handler);


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
	 * Handler to redirect to the correct notification widget the
	 * notify user event.
	 * @param {HTMLElement} sourceElement The element that issued the
	 * "notifyUser" event.
	 * @param {Event} event The "notify user" event.
	 * @param {Object} params The event parameters. Can be the element
	 * that triggered the event or the notification content.
	 * @private
	 */
	$.pkp.controllers.PageHandler.prototype.redirectNotifyUserEventHandler_ =
			function(sourceElement, event, params) {

		if (params.status == true) {
			this.getHtmlElement().parent().trigger('notifyUser', params);
		} else {
			// Use the notification helper to redirect the notify user event.
			$.pkp.classes.notification.NotificationHelper.
					redirectNotifyUserEvent(this, params);
		}

	};

	/**
	 * Handler to redirect to the correct grid the dataChanged event.
	 * @param {HTMLElement} sourceElement The element that issued the
	 * "dataChanged" event.
	 * @param {Event} event The "data changed" event.
	 * @private
	 */
	$.pkp.controllers.PageHandler.prototype.redirectDataChangedEventHandler_ =
		function(sourceElement, event) {

		// Get the link action element (that is outside of any grid)
		// that triggered the event.
		var $sourceLinkElement = $('a', event.target);

		// Get all grids inside this widget that have a
		// link action with the same url of the sourceLinkElement.
		var $grids = $('.pkp_controllers_grid', this.getHtmlElement()).has('a[href=' + $sourceLinkElement.attr('href') + ']');

		// Trigger the dataChanged event on found grids,
		// so they can refresh themselves.
		if ($grids.length > 0) {
			$grids.each(function() {
				// Keyword "this" is being used here in the
				// context of the grid html element.
				$(this).trigger('dataChanged');
			});
		}
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);
