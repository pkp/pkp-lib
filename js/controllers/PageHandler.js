/**
 * @file js/controllers/PageHandler.js
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
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
		this.bind('redirectDataChangedToGrid', this.redirectDataChangedEventHandler_);

		// Listen to this event to be able to update the correspondent
		// grid. Look the handler method description to understand how
		// to use this.
		this.bind('gridRefreshRequested', this.gridRefreshRequestedHandler_);
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

		if (params.status === true) {
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
	 * @param {Event} event The "redirect data changed" event.
	 * @param {object} eventData The data changed event data.
	 * @private
	 */
	$.pkp.controllers.PageHandler.prototype.redirectDataChangedEventHandler_ =
			function(sourceElement, event, eventData) {

		// Get the link action element (that is outside of any grid)
		// that triggered the redirect event.
		var $sourceLinkElement = $('a', event.target);
		var linkActionHandler = $.pkp.classes.Handler.getHandler($sourceLinkElement);
		var linkUrl = linkActionHandler.getUrl();

		// Get all grids inside this widget that have a
		// link action with the same url of the sourceLinkElement.
		var $grids = $('.pkp_controllers_grid', this.getHtmlElement())
				.has('a[href=' + linkUrl + ']');

		// Trigger the dataChanged event on found grids,
		// so they can refresh themselves.
		if ($grids.length > 0) {
			$grids.each(function() {
				// Keyword "this" is being used here in the
				// context of the grid html element.
				$(this).trigger('dataChanged', eventData);
			});
		}
	};


	/**
	 * Handler to update a grid element when another one is updated.
	 * This method expects two type of elements:
	 * 1) THE SOURCE: the element that triggered the refreshRequested event;
	 * 2) THE TARGET: any number of grid elements;
	 *
	 * It finds both type of elements using class definition, so
	 * you must define classes in the template file in order to tell
	 * which grid should update the other(s). The classes are:
	 * - 'update_source' for the source element;
	 * - the source element id string for the target element;
	 *
	 * When a grid is refreshed, this handler will search for the
	 * 'update_source' string on every grid style class. If it's found,
	 * it will search for other grids on this page with a class
	 * equals to source element id string. Any grid with this class will have
	 * the 'dataChanged' event triggered.
	 *
	 * With those 2 definitions (source and target(s)), this method
	 * can handle with any number of updating grid processes at the same
	 * time. Search for 'update_source' in the template files for examples.
	 * @param {HTMLElement} sourceElement The element that issued the
	 * "gridRefreshRequested" event.
	 * @param {Event} event The "grid refresh requested" event.
	 * @private
	 */
	$.pkp.controllers.PageHandler.prototype.gridRefreshRequestedHandler_ =
			function(sourceElement, event) {
		var updateSourceClassString = 'update_source';

		var $updateSourceElement = $(event.target);
		var updateSourceElementClasses =
				$updateSourceElement.attr('class').split(' ');
		for (var key in updateSourceElementClasses) {
			if (updateSourceElementClasses[key].search(updateSourceClassString) != -1) {
				var updatableElementsId = $updateSourceElement.attr('id');

				var $targetElements = $(this.getHtmlElement())
						.find('.' + updatableElementsId);
				if ($targetElements.length > 0) {
					var $grids = $targetElements.find('.pkp_controllers_grid');
					if ($grids.length > 0) {
						$grids.each(function() {
							// Keyword "this" is being used here in the
							// context of the grid html element.
							$(this).trigger('dataChanged');
						});
					}
				}
				break; // Already found the source class definition.
			}
		}
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);
