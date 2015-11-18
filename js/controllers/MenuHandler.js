/**
 * @file js/controllers/MenuHandler.js
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MenuHandler
 * @ingroup js_controllers
 *
 * @brief A basic handler for a hierarchical list of navigation items.
 *
 * Attach this handler to a <ul> with nested <li> and <ul> elements.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQueryObject} $menu The outer <ul> element
	 * @param {Object} options Handler options.
	 */
	$.pkp.controllers.MenuHandler = function($menu, options) {

		this.parent($menu, options);

		// Reference to all links within the menu
		this.$links_ = this.getHtmlElement().find('a');
		this.$parents_ = this.getHtmlElement().find('.has-submenu');

		// Fix dropdown menus that may go off-screen and recalculate whenever
		// the browser window is resized
		this.setDropdownAlignment();
		$(window).resize(this.callbackWrapper(this.onResize));


		// Attach event handlers
		this.$links_.bind('focus', this.onFocus);
		this.$links_.bind('blur', this.onBlur);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.MenuHandler, $.pkp.classes.Handler);


	//
	// Protected methods
	//
	/**
	 * Event handler that is called when a link within the menu gets focus.
	 *
	 * @param {Event} event The triggered event.
	 */
	$.pkp.controllers.MenuHandler.prototype.onFocus = function(event) {
		var parent = $(event.target).parents('li');
		if (!parent.length) {
			return;
		}

		parent.addClass('in_focus');
	};


	/**
	 * Event handler that is called when a link within the menu loses focus.
	 *
	 * @param {Event} event The triggered event.
	 */
	$.pkp.controllers.MenuHandler.prototype.onBlur = function(event) {
		var parent = $(event.target).parents('li');
		if (!parent.length) {
			return;
		}

		parent.removeClass('in_focus');
	};


	/**
	 * Attach a class to any dropdown menus that will stray off-screen to align
	 * them to the right edge of their parent
	 */
	$.pkp.controllers.MenuHandler.prototype.setDropdownAlignment = function() {
		var width = Math.max(
				document.documentElement.clientWidth, window.innerWidth || 0);
		this.$parents_.each(function() {
			var right = $(this).offset().left + $(this).children('ul').outerWidth();
			if (right > width) {
				$(this).addClass('align_right');
			} else {
				$(this).removeClass('align_right');
			}
		});
	};


	/**
	 * Throttle the dropdown alignment check during resize events. During
	 * browser resizing this will fire off every single frame, causing lag
	 * during the resize. So this just throttles the actual alignment check
	 * function by only firing when resizing has stopped.
	 */
	$.pkp.controllers.MenuHandler.prototype.onResize = function() {
		clearTimeout(this.resize_check);
		this.resize_check = setTimeout(
				this.callbackWrapper(this.setDropdownAlignment), 1000);
	};

/** @param {jQuery} $ jQuery closure. */
}(jQuery));
