/**
 * @file js/controllers/TabHandler.js
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class TabHandler
 * @ingroup js_controllers
 *
 * @brief A basic handler for a tabbed set of pages.
 *
 * See <http://jqueryui.com/demos/tabs/> for documentation on JQuery tabs.
 * Attach this handler to a div that contains a <ul> with a <li> for each tab
 * to be created.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQueryObject} $tabs A wrapped HTML element that
	 *  represents the tabbed interface.
	 * @param {Object} options Handler options.
	 */
	$.pkp.controllers.TabHandler = function($tabs, options) {
		var pageUrl, pageAnchor, pattern, pageAnchors, tabAnchors, i;

		this.parent($tabs, options);

		// Attach the tabs event handlers.
		this.bind('tabsbeforeactivate', this.tabsBeforeActivate);
		this.bind('tabsactivate', this.tabsActivateCreate);
		this.bind('tabscreate', this.tabsActivateCreate);
		this.bind('tabsload', this.tabsLoad);
		this.bind('containerReloadRequested', this.tabsReloadRequested);
		this.bind('addTab', this.addTab);

		if (options.emptyLastTab) {
			this.emptyLastTab_ = options.emptyLastTab;
		}

		// Render the tabs as jQueryUI tabs.
		$tabs.tabs({
			// Enable AJAX-driven tabs with JSON messages.
			ajaxOptions: {
				cache: false,
				dataFilter: this.callbackWrapper(this.dataFilter)
			},
			disabled: options.disabled
		});

		// if the page has been loaded with an #anchor
		// determine what tab that is for and set the
		// options.selected value to it so it gets used
		// when tabs() are initialized.
		pageUrl = document.location.toString();
		if (pageUrl.match('#')) {
			pageAnchor = pageUrl.split('#')[1];
			tabAnchors = $tabs.find('li a');
			for (i = 0; i < tabAnchors.length; i++) {
				if (pageAnchor == tabAnchors[i].getAttribute('name')) {
					// Matched on anchor name.
					options.selected = i;
				} else {
					// Try to match on anchor href.
					pattern = new RegExp('[/=]' + pageAnchor + '([?]|$)');
					if (tabAnchors[i].getAttribute('href').match(pattern)) {
						options.selected = i;
					}
				}
			}
		}

		$tabs.tabs({selected: options.selected});

		if ($tabs.find('.stTabsInnerWrapper').length == 0 && !options.notScrollable) {
			$tabs.tabs().scrollabletab();
		}
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.TabHandler, $.pkp.classes.Handler);


	//
	// Private properties
	//
	/**
	 * The current tab.
	 * @private
	 * @type {jQueryObject}
	 */
	$.pkp.controllers.TabHandler.prototype.$currentTab_ = null;


	/**
	 * The current tab index.
	 * @private
	 * @type {number}
	 */
	$.pkp.controllers.TabHandler.prototype.currentTabIndex_ = 0;


	/**
	 * Whether to empty the previous tab when switching to a new one
	 * @private
	 * @type {boolean}
	 */
	$.pkp.controllers.TabHandler.prototype.emptyLastTab_ = false;


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
	$.pkp.controllers.TabHandler.prototype.tabsBeforeActivate =
			function(tabsElement, event, ui) {

		var unsavedForm = false;
		this.$currentTab_.find('form').each(function(index) {

			var handler = $.pkp.classes.Handler.getHandler($('#' + $(this).attr('id')));
			if (handler.formChangesTracked) {
				unsavedForm = true;
				return false; // found an unsaved form, no need to continue with each().
			}
		});

		this.$currentTab_.find('.hasDatepicker').datepicker('hide');

		if (unsavedForm) {
			if (!confirm($.pkp.locale.form_dataHasChanged)) {
				return false;
			} else {
				this.trigger('unregisterAllForms');
			}
		}

		if (this.emptyLastTab_) {
			// bind a single (i.e. one()) error event handler to prevent
			// propagation if the tab being unloaded no longer exists.
			// We cannot simply getHandler() since that in of itself throws
			// an Error.
			$(window).one('error', function(msg, url, line) { return false; });
			if (this.$currentTab_) {
				this.$currentTab_.empty();
			}
		}
		return true;
	};

	/**
	 * Event handler that is called when a tab is activated or created.
	 *
	 * @param {HTMLElement} tabsElement The tab element that triggered
	 *  the event.
	 * @param {Event} event The triggered event.
	 * @param {{panel: jQueryObject}} ui The tabs ui data.
	 * @return {boolean} Should return true to continue tab loading.
	 */
	$.pkp.controllers.TabHandler.prototype.tabsActivateCreate =
			function(tabsElement, event, ui) {

		var tab = (event.type == 'tabscreate') ? ui.panel : ui.newTab;

		// Save a reference to the current tab.
		this.$currentTab_ = tab.jquery ? tab : $(tab);

		// Save the tab index.
		if (event.type == 'tabsactivate') this.currentTabIndex_ = tab.index();

		return true;
	};

	/**
	 * Event handler that is called after a remote tab was loaded.
	 *
	 * @param {HTMLElement} tabsElement The tab element that triggered
	 *  the event.
	 * @param {Event} event The triggered event.
	 * @param {Object} ui The tabs ui data.
	 * @return {boolean} Should return true to continue tab loading.
	 */
	$.pkp.controllers.TabHandler.prototype.tabsLoad =
			function(tabsElement, event, ui) {
		return true;
	};

	/**
	 * Callback that that is triggered before the tab is loaded
	 *
	 * @param {HTMLElement} tabsElement The tab element that triggered
	 *  the event.
	 * @param {Event} event The triggered event.
	 * @param {Object} ui The tabs ui data.
	 */
	$.pkp.controllers.TabHandler.prototype.beforeLoad =
			function(tabsElement, event, ui) {
		ui.ajaxSettings.cache = false;
		ui.ajaxSettings.dataFilter = this.callbackWrapper(this.dataFilter);
	};

	/**
	 * Callback that processes AJAX data returned by the server before
	 * it is displayed in a tab.
	 *
	 * @param {Object} ajaxOptions The options object from which the
	 *  callback originated.
	 * @param {string} jsonString Unparsed JSON data returned from the server.
	 * @return {string} The tab mark-up.
	 */
	$.pkp.controllers.TabHandler.prototype.dataFilter =
			function(ajaxOptions, jsonString) {

		var jsonData = this.handleJson($.parseJSON(jsonString));
		if (jsonData === false) {
			return '';
		}
		return jsonData.content;
	};


	/**
	 * Callback that processes data returned by the server when
	 * a 'tabsReloadRequested' event is bubbled up from a widget
	 * on a tab.
	 *
	 * This is useful if you have a tabbed form on a modal and you
	 * wish to reload the entire tabbed interface when one form is
	 * submitted. Since this reloads the templates for the tabs you
	 * have the opportunity to alter their state.
	 *
	 * @param {HTMLElement} divElement The parent DIV element
	 *  which contains the tabs.
	 * @param {Event} event The triggered event (tabsReloadRequested).
	 * @param {{tabsUrl: string}} jsonContent The tabs ui data.
	 */
	$.pkp.controllers.TabHandler.prototype.tabsReloadRequested =
			function(divElement, event, jsonContent) {

		var $element = this.getHtmlElement();
		$.get(jsonContent.tabsUrl, function(data) {
			var jsonData = $.parseJSON(data);
			$element.replaceWith(jsonData.content);
		});
	};


	/**
	 * Callback that processes data returned by the server when
	 * an 'addTab' event is received.
	 *
	 * This is useful e.g. when the results of a form handler
	 * should be sent to a different tab in the containing tabset.
	 *
	 * @param {HTMLElement} divElement The parent DIV element
	 *  which contains the tabs.
	 * @param {Event} event The triggered event (addTab).
	 * @param {{url: string, title: string}} jsonContent The tabs ui data.
	 */
	$.pkp.controllers.TabHandler.prototype.addTab =
			function(divElement, event, jsonContent) {

		var $element = this.getHtmlElement();
		$element.tabs('add', jsonContent.url, jsonContent.title)
				.tabs('option', 'active', $element.tabs('length') - 1);
	};


	//
	// Protected methods
	//
	/**
	 * Get the current tab.
	 * @protected
	 * @return {jQueryObject} The current tab.
	 */
	$.pkp.controllers.TabHandler.prototype.getCurrentTab = function() {
		return this.$currentTab_;
	};


	/**
	 * Get the current tab index.
	 * @protected
	 * @return {number} The current tab index.
	 */
	$.pkp.controllers.TabHandler.prototype.getCurrentTabIndex = function() {
		return this.currentTabIndex_;
	};


/** @param {jQuery} $ jQuery closure. */
}(jQuery));
