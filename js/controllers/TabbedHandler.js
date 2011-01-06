/**
 * @defgroup js_controllers
 */
// Create the modal namespace.
jQuery.pkp.controllers = jQuery.pkp.controllers || { };

/**
 * @file js/controllers/TabbedHandler.js
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class TabbedHandler
 * @ingroup js_controllers
 *
 * @brief Basic tabbed modal handler.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQuery} $modal A wrapped HTML element that
	 *  represents the tabbed modal.
	 */
	$.pkp.controllers.TabbedHandler = function($modal) {
		this.parent($modal);

		// Attach the tabs event handlers.
		this.bind('tabsshow', this.tabsShow);
		this.bind('tabsselect', this.tabsSelect);

		// Render the tabs as jQueryUI tabs.
		$modal.tabs({
			// Enable AJAX-driven tabs with JSON messages.
			ajaxOptions: {
				dataFilter: this.callbackWrapper(this.dataFilter)
			},
			selected: 0
		});
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.TabbedHandler, $.pkp.classes.Handler);


	//
	// Private properties
	//
	/**
	 * The current tab.
	 * @private
	 * @type {jQuery}
	 */
	$.pkp.controllers.TabbedHandler.prototype.$currentTab_ = null;


	//
	// Public methods
	//
	/**
	 * Event handler that is called when a tab is selected.
	 *
	 * @param {HTMLElement} tabsElement The tab element that triggered
	 *  the event.
	 * @param {Event} event The triggered event.
	 * @param {Object} ui The tabs ui data.
	 * @return {boolean} Should return true to continue tab loading.
	 */
	$.pkp.controllers.TabbedHandler.prototype.tabsSelect =
			function(tabsElement, event, ui) {

		// The default implementation does nothing.
		return true;
	};


	/**
	 * Event handler that is called when a tab is shown.
	 *
	 * @param {HTMLElement} tabsElement The tab element that triggered
	 *  the event.
	 * @param {Event} event The triggered event.
	 * @param {Object} ui The tabs ui data.
	 * @return {boolean} Should return true to continue tab loading.
	 */
	$.pkp.controllers.TabbedHandler.prototype.tabsShow =
			function(tabsElement, event, ui) {

		// Save a reference to the current tab.
		this.$currentTab_ = (ui.panel.jquery ? ui.panel : $(ui.panel));
		return true;
	};


	/**
	 * Callback that processes AJAX data returned by the server before
	 * it is displayed in a tab.
	 *
	 * @param {Object} ajaxOptions The options object from which the
	 *  callback originated.
	 * @param {Object} jsonData The data returned from an AJAX call.
	 * @return {string} The tab mark-up.
	 */
	$.pkp.controllers.TabbedHandler.prototype.dataFilter =
			function(ajaxOptions, jsonData) {

		var data = $.parseJSON(jsonData);
		if (data.status === true) {
			return data.content;
		} else {
			alert(data.content);
		}
		return '';
	};


	//
	// Protected methods
	//
	/**
	 * Get the current tab.
	 * @protected
	 * @return {jQuery} The current tab.
	 */
	$.pkp.controllers.TabbedHandler.prototype.getCurrentTab = function() {
		return this.$currentTab_;
	};

/** @param {jQuery} $ jQuery closure. */
})(jQuery);
