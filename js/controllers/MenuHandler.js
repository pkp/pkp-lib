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
		this.$links_ = this.$htmlElement_.find( 'a' );

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
		var parent = $(event.target).parents( 'li' );
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
		var parent = $(event.target).parents( 'li' );
		if (!parent.length) {
			return;
		}

		parent.removeClass('in_focus');
	};



/** @param {jQuery} $ jQuery closure. */
}(jQuery));
