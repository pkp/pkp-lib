/**
 * @file js/controllers/RevealMoreHandler.js
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RevealMoreHandler
 * @ingroup js_controllers
 *
 * @brief A basic handler for the reveal more UI pattern
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQueryObject} $widgetWrapper An HTML element that contains the
	 *   widget.
	 * @param {Object} options Handler options.
	 */
	$.pkp.controllers.RevealMoreHandler = function($widgetWrapper, options) {
		this.parent($widgetWrapper, options);

		if ($widgetWrapper.outerHeight() > options.height) {
			$widgetWrapper.addClass('isHidden').
					css('max-height', options.height + 'px');
			$('.revealMoreButton', $widgetWrapper).click(
					this.callbackWrapper(this.revealMore));
		}
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.RevealMoreHandler, $.pkp.classes.Handler);


	//
	// Public methods
	//
	/**
	 * Event handler that is called when the button to reveal more is clicked
	 *
	 * @param {HTMLElement} revealMoreButton The button that is clicked to
	 *   toggle extras.
	 * @param {Event} event The triggering event.
	 */
	$.pkp.controllers.RevealMoreHandler.prototype.revealMore =
			function(revealMoreButton, event) {
		this.getHtmlElement().removeClass('isHidden').removeAttr('style');
		event.preventDefault();
		event.stopPropagation();
	};

/** @param {jQuery} $ jQuery closure. */
}(jQuery));
