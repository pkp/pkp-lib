/**
 * @file js/controllers/ExtrasOnDemandHandler.js
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ExtrasOnDemandHandler
 * @ingroup js_controllers
 *
 * @brief A basic handler for extras on demand UI pattern.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQueryObject} $widgetWrapper An HTML element that contains the
	 *  widget.
	 * @param {Object} options Handler options.
	 */
	$.pkp.controllers.ExtrasOnDemandHandler = function($widgetWrapper, options) {
		this.parent($widgetWrapper, options);

		// Show the toggle button and attach click event
		// to it. We hide this by default to provide a graceful
		// fallback in case JS is switched off.
		$('.toggleExtras', $widgetWrapper).show().click(
				this.callbackWrapper(this.toggleExtras));

		// Hide extras (default initial widget state).
		this.deactivateExtraContent_();
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.ExtrasOnDemandHandler, $.pkp.classes.Handler);


	//
	// Public methods
	//
	/**
	 * Event handler that is called when toggle extras div is clicked.
	 *
	 * @param {HTMLElement} toggleExtras The div that is clicked to toggle extras.
	 */
	$.pkp.controllers.ExtrasOnDemandHandler.prototype.toggleExtras =
			function(toggleExtras) {

		var $widgetWrapper = this.getHtmlElement();

		if ($(toggleExtras, $widgetWrapper).hasClass('active')) {
			this.deactivateExtraContent_('slow');
		} else {
			this.activateExtraContent_('slow');
		}
	};


	//
	// Private methods
	//
	/**
	 * Activate extra content.
	 * @private
	 * @param {string=} opt_duration The effect duration.
	 */
	$.pkp.controllers.ExtrasOnDemandHandler.prototype.activateExtraContent_ =
			function(opt_duration) {
		var $widgetWrapper, $scrollable;

		$widgetWrapper = this.getHtmlElement();

		// Hide the inactive version of the toggle extras span.
		$('.toggleExtras .toggleExtras-inactive', $widgetWrapper).hide();

		// Show the active version of the toggle extras span and the extras container.
		$('.extrasContainer', $widgetWrapper).show(opt_duration);
		$('.toggleExtras .toggleExtras-active', $widgetWrapper).show();

		// Adapt styling of the toggle extras div.
		$('.toggleExtras', $widgetWrapper).removeClass('inactive').addClass('active');

		// Change the toggle icon into a triangle pointing downwards.
		$('.ui-icon', $widgetWrapper)
				.removeClass('ui-icon-triangle-1-e')
				.addClass('ui-icon-triangle-1-s');

		// Identify if there is a scrollable parent.
		$scrollable = $widgetWrapper.closest('.scrollable');
		if ($scrollable.size() > 0) {

			// Scroll the parent so that all extra content in
			// extras container is visible.
			if ($.browser.msie && parseInt(
					$.browser.version.substring(0, 1), 10) <= 7) {

				// IE7 is old and slow and returns before repainting everything,
				// so wait half a second for the page to repaint before going on.
				setTimeout(function() {this.scrollToMakeVisible_(
						$widgetWrapper, $scrollable);}, 500);
			} else {

				// Other browsers can proceed immediately.
				this.scrollToMakeVisible_($widgetWrapper, $scrollable);
			}
		}
	};


	/**
	 * Deactivate extra content.
	 * @private
	 * @param {string=} opt_duration The effect duration.
	 */
	$.pkp.controllers.ExtrasOnDemandHandler.prototype.deactivateExtraContent_ =
			function(opt_duration) {

		var $widgetWrapper = this.getHtmlElement();

		// Hide the active version of the toggle extras span and the extras container.
		$('.extrasContainer', $widgetWrapper).hide(opt_duration);
		$('.toggleExtras .toggleExtras-active', $widgetWrapper).hide();

		// Show the inactive version of the toggle extras span.
		$('.toggleExtras .toggleExtras-inactive', $widgetWrapper).show();

		// Adapt styling of the toggle extras div.
		$('.toggleExtras', $widgetWrapper).removeClass('active').addClass('inactive');

		// Change the toggle icon into a triangle pointing to the right.
		$('.ui-icon', $widgetWrapper).removeClass('ui-icon-triangle-1-s')
				.addClass('ui-icon-triangle-1-e');
	};


	/**
	 * Scroll a scrollable element to make the
	 * given content element visible. The content element
	 * must be a descendant of a scrollable
	 * element (needs to have class "scrollable").
	 *
	 * NB: This method depends on the position() method
	 * to refer to the same parent element for both the
	 * content element and the scrollable element.
	 *
	 * @private
	 *
	 * @param {jQueryObject} $widgetWrapper The element to be made visible.
	 * @param {Array|jQueryObject} $scrollable The parent scrollable element.
	 */
	$.pkp.controllers.ExtrasOnDemandHandler.prototype.scrollToMakeVisible_ =
			function($widgetWrapper, $scrollable) {
		var extrasWidgetTop, scrollingWidgetTop, currentScrollingTop,
				hiddenPixels, newScrollingTop;

		extrasWidgetTop = $widgetWrapper.position().top;
		scrollingWidgetTop = $scrollable.position().top;
		currentScrollingTop = parseInt($scrollable.scrollTop(), 10);

		// Do we have to scroll down or scroll up?
		if (extrasWidgetTop > scrollingWidgetTop) {
			// Consider scrolling down...

			// Calculate the number of hidden pixels of the child
			// element within the scrollable element.
			hiddenPixels = Math.ceil(extrasWidgetTop +
					$widgetWrapper.height() - $scrollable.height());

			// Scroll down if parts or all of this widget are hidden.
			if (hiddenPixels > 0) {
				$scrollable.scrollTop(currentScrollingTop + hiddenPixels);
			}
		} else {
			// Scroll up...

			// Calculate the new scrolling top.
			newScrollingTop = Math.max(Math.floor(
					currentScrollingTop + extrasWidgetTop - scrollingWidgetTop), 0);

			// Set the new scrolling top.
			$scrollable.scrollTop(newScrollingTop);
		}
	};


/** @param {jQuery} $ jQuery closure. */
}(jQuery));
