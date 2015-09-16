/**
 * @file js/controllers/HelpPanelHandler.js
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class HelpPanelHandler
 * @ingroup js_controllers
 *
 * @brief A handler for the fly-out contextual help panel.
 *
 * @listens pkp.HelpPanel.Open
 * @listens pkp.HelpPanel.Close
 * @emits pkp.HelpPanel.Open
 * @emits pkp.HelpPanel.Close
 *
 * This handler expects to be be attached to an element which matches the
 * following base markup. There should only be one help panel on any page.
 *
 * <div id="pkpHelpPanel" tabindex="-1">
 *   <div>
 *     <!-- This handler should only ever interact with the .content div. -->
 *     <div class="content"></div>
 *     <button class="pkpCloseHelpPanel"></button>
 *   </div>
 * </div>
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQueryObject} $element The outer <div> element
	 */
	$.pkp.controllers.HelpPanelHandler = function($element) {

		this.parent($element, {});

		// Search dom for calling elements and register click handlers
		$('body').find('.requestHelpPanel').click(function(e) {
			e.preventDefault();
			var options = $.extend({}, $(this).data(), { caller: $(this) } );
			$element.trigger('pkp.HelpPanel.Open', options);
		});

		// Register click handler on close button
		$element.find('.pkpCloseHelpPanel').click(function(e) {
			e.preventDefault();
			$element.trigger('pkp.HelpPanel.Close');
		});

		// Register listeners
		$element.on('pkp.HelpPanel.Open', this.openPanel)
		        .on('pkp.HelpPanel.Close', this.closePanel);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.HelpPanelHandler, $.pkp.classes.Handler);


	//
	// Private properties
	//
	/**
	 * Calling element. Focus will be returned here when help panel is closed
	 * @private
	 * @type {jQueryObject}
	 */
	$.pkp.controllers.HelpPanelHandler.prototype._caller = null;



	//
	// Protected methods
	//
	/**
	 * Open the helper panel
	 *
	 * @param {Event} event The event triggered on this handler
	 * @param {object} options The options with which to open this handler
	 *  triggered this event if one exists. Usually a link.
	 */
	$.pkp.controllers.HelpPanelHandler.prototype.openPanel = function(event, options) {

		// Get a reference to this handler
		var helpPanelHandler = $.pkp.classes.Handler.getHandler($(this));

		// Save the calling element
		if (typeof options.caller !== 'undefined') {
			helpPanelHandler._caller = options.caller;
		}

		// Show the help panel
		$(this).addClass('is_visible');
		$('body').addClass('help_panel_is_visible'); // manage scrollbars

		// Listen to close interaction events
		$(this).on('click keyup', helpPanelHandler.handleWrapperEvents);

		// Load the appropriate help content
		// @todo Use options.topic to retrieve the content and place it into
		//  $(this).find('.content')

		// Set focus inside the help panel (delay is required so that element is
		// visible when jQuery tries to focus on it)
		// @todo This should only happen once content is loaded in
		setTimeout(function() {
			helpPanelHandler.getHtmlElement().focus();
		}, 300);

	};


	/**
	 * Close the helper panel
	 */
	$.pkp.controllers.HelpPanelHandler.prototype.closePanel = function() {

		// Get a reference to this handler
		var helpPanelHandler = $.pkp.classes.Handler.getHandler($(this));

		// Show the help panel
		$(this).removeClass('is_visible');
		$('body').removeClass('help_panel_is_visible'); // manage scrollbars

		// Clear the help content
		helpPanelHandler.getHtmlElement().find('.content').empty();

		// Set focus back to the calling element
		if (helpPanelHandler._caller !== null) {
			helpPanelHandler._caller.focus();
		}
	};


	/**
	 * Process events that reach the wrapper element.
	 *
	 * @param {Event} event The event triggered on this handler
	 */
	$.pkp.controllers.HelpPanelHandler.prototype.handleWrapperEvents =
			function(event) {

		// Close click events directly on modal (background screen)
		if (event.type == 'click' && $(this).is(event.target)) {
			$(this).trigger('pkp.HelpPanel.Close');
			return;
		}

		// Close for ESC keypresses (27)
		if (event.type == 'keyup' && event.which == 27) {
			$(this).trigger('pkp.HelpPanel.Close');
			return;
		}
	};



/** @param {jQuery} $ jQuery closure. */
}(jQuery));
