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
		// 1ms delay allows dom insertion to complete
		var self = this;
		setTimeout(function() {
			self.callbackWrapper(self.setDropdownAlignment())
		}, 1);
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
	 * Check if submenus are straying off-screen and adjust as needed
	 */
	$.pkp.controllers.MenuHandler.prototype.setDropdownAlignment = function() {
		var $this = $(this),
			width = Math.max(
				document.documentElement.clientWidth, window.innerWidth || 0),
			height = Math.max(
				document.documentElement.clientHeight, window.innerHeight || 0);

		this.$parents_.each(function() {
			$parent = $(this);
			$submenus = $parent.children('ul');

			// Width
			var right = $parent.offset().left + $submenus.outerWidth();
			if (right > width) {
				$parent.addClass('align_right');
			} else {
				$parent.removeClass('align_right');
			}

			// Height
			$submenus.attr( 'style', '' ); // reset
			var pos_top = $parent.offset().top;
			var pos_btm = pos_top + $submenus.outerHeight();
			var needs_scrollbar = false;
			if (pos_btm > height) {
				var offset_top = pos_btm - height;
				var new_top = pos_top - offset_top;
				if (new_top < 0) {
					offset_top += new_top;
					$submenus.css('overflow-y', 'scroll');
					$submenus.css('bottom', -Math.abs(height - pos_top - $parent.outerHeight()) + 'px' );
				}
				$submenus.css('top', -Math.abs(offset_top) + 'px');
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
