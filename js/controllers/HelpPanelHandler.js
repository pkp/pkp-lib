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
 * Listens: pkp.HelpPanel.Open
 * Listens: pkp.HelpPanel.Close
 * Emits: pkp.HelpPanel.Open
 * Emits: pkp.HelpPanel.Close
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
	 * @param {Object} options Handler options.
	 */
	$.pkp.controllers.HelpPanelHandler = function($element, options) {

		this.parent($element, {});

		// Search dom for calling elements and register click handlers
		$('body').find('.requestHelpPanel').click(function(e) {
			e.preventDefault();
			var $self = $(this),
					options = $.extend({}, $self.data(), {caller: $self});
			$element.trigger('pkp.HelpPanel.Open', options);
		});

		// Register click handler on close button
		$element.find('.pkpCloseHelpPanel').click(function(e) {
			e.preventDefault();
			$element.trigger('pkp.HelpPanel.Close');
		});

		// Register click handler on home button
		$element.find('.pkpHomeHelpPanel').click(function(e) {
			e.preventDefault();
			$element.trigger('pkp.HelpPanel.Home');
		});

		// Register listeners
		$element.on('pkp.HelpPanel.Open', this.callbackWrapper(this.openPanel_))
			.on('pkp.HelpPanel.Close', this.callbackWrapper(this.closePanel_))
			.on('pkp.HelpPanel.Home', this.callbackWrapper(this.homePanel_));

		this.helpUrl_ = options.helpUrl;

		// If a page-wide help context was set, send it to the SiteHandler.
		if (options.helpContext) {
			$element.trigger('setHelpContext', options.helpContext);
		}
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
	$.pkp.controllers.HelpPanelHandler.prototype.caller_ = null;


	/**
	 * Help subsystem's URL. Used to fetch help content for presentation.
	 * @private
	 * @type {string?}
	 */
	$.pkp.controllers.HelpPanelHandler.prototype.helpUrl_ = null;


	//
	// Private methods
	//
	/**
	 * Open the helper panel
	 * @private
	 * @param {HTMLElement} context The context in which this function was called
	 * @param {Event} event The event triggered on this handler
	 * @param {Object} options The options with which to open this handler
	 */
	$.pkp.controllers.HelpPanelHandler.prototype.openPanel_ =
			function(context, event, options) {
		var $element = this.getHtmlElement(),
				siteHandler = $.pkp.classes.Handler.getHandler($('body'));

		// Save the calling element
		if (typeof options.caller !== 'undefined') {
			this.caller_ = options.caller;
		}

		// Show the help panel
		$element.addClass('is_visible');
		$('body').addClass('help_panel_is_visible'); // manage scrollbars

		// Listen to close interaction events
		$element.on('click.pkp.HelpPanel keyup.pkp.HelpPanel',
				this.callbackWrapper(this.handleWrapperEvents));

		// Load the appropriate help content
		this.loadHelpContent_(siteHandler.getHelpContext());

		// Set focus inside the help panel (delay is required so that element is
		// visible when jQuery tries to focus on it)
		// @todo This should only happen once content is loaded in
		setTimeout(function() {
			$element.focus();
		}, 300);

	};


	/**
	 * Load help content in the panel.
	 * @param {string?} helpContext The help context.
	 * @private
	 */
	$.pkp.controllers.HelpPanelHandler.prototype.loadHelpContent_ =
			function(helpContext) {
		if (helpContext === null) {
			helpContext = '';
		}
		$.get(this.helpUrl_.replace(
				'HELP_CONTEXT_SLUG', encodeURIComponent(helpContext)),
				null, this.callbackWrapper(this.updateContentHandler_), 'json');
	};


	/**
	 * A callback to update the tabs on the interface.
	 * @private
	 * @param {Object} ajaxContext The AJAX request context.
	 * @param {Object} jsonData A parsed JSON response object.
	 */
	$.pkp.controllers.HelpPanelHandler.prototype.
			updateContentHandler_ = function(ajaxContext, jsonData) {
		var workingJsonData = this.handleJson(jsonData), helpPanelHandler = this,
				siteHandler = $.pkp.classes.Handler.getHandler($('body')),
				$element = this.getHtmlElement(),
				helpContext = siteHandler.getHelpContext(),
				hashIndex = siteHandler.getHelpContext().indexOf('#'),
				$targetHash;

		// Place the new content into the DOM
		$element.find('.content').replaceWith(
				'<div class="content">' + workingJsonData.content + '</div>');

		// If a hash was specified, scroll to the named anchor.
		if (hashIndex !== -1) {
			$targetHash = $element.find(
					'a[name=' + helpContext.substr(hashIndex + 1) + ']');
			$element.find('.panel').scrollTop(
					$targetHash.offset().top);
		}

		// Make sure clicks within help content are handled properly
		$element.find('.content').find('a').click(function(e) {
			e.preventDefault();
			helpPanelHandler.loadHelpContent_(
					/** @type {string} */ ($(e.target).attr('href')));
		});
	};


	/**
	 * Close the helper panel
	 * @private
	 */
	$.pkp.controllers.HelpPanelHandler.prototype.closePanel_ = function() {

		// Get a reference to this handler's element as a jQuery object
		var $element = this.getHtmlElement();

		// Show the help panel
		$element.removeClass('is_visible');
		$('body').removeClass('help_panel_is_visible'); // manage scrollbars

		// Clear the help content
		$element.find('.content').empty();

		// Set focus back to the calling element
		if (this.caller_ !== null) {
			this.caller_.focus();
		}

		// Unbind wrapper events from element and reset vars
		$element.off('click.pkp.HelpPanel keyup.pkp.HelpPanel');
		this.caller_ = null;
	};


	/**
	 * Home the helper panel
	 * @private
	 */
	$.pkp.controllers.HelpPanelHandler.prototype.homePanel_ = function() {
		this.loadHelpContent_(null);
	};


	/**
	 * Process events that reach the wrapper element.
	 *
	 * @param {HTMLElement} context The context in which this function was called
	 * @param {Event} event The event triggered on this handler
	 */
	$.pkp.controllers.HelpPanelHandler.prototype.handleWrapperEvents =
			function(context, event) {

		// Get a reference to this handler's element as a jQuery object
		var $element = this.getHtmlElement();

		// Close click events directly on modal (background screen)
		if (event.type == 'click' && $element.is($(event.target))) {
			$element.trigger('pkp.HelpPanel.Close');
			return;
		}

		// Close for ESC keypresses (27)
		if (event.type == 'keyup' && event.which == 27) {
			$element.trigger('pkp.HelpPanel.Close');
			return;
		}
	};



/** @param {jQuery} $ jQuery closure. */
}(jQuery));
