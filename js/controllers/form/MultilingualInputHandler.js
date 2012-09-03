/**
 * @file js/controllers/form/MultilingualInputHandler.js
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MultilingualInputHandler
 * @ingroup js_controllers_form
 *
 * @brief Handler for toggling the pop-over on multi lingual inputs (text
 * inputs and text areas, mostly).
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQuery} $popover the wrapped HTML element.
	 * @param {Object} options options to be passed
	 *  into the validator plug-in.
	 */
	$.pkp.controllers.form.MultilingualInputHandler = function($popover, options) {
		this.parent($popover, options);

		// Bind to the focus of the primary language (the first input)
		// open the pop-over
		var $popoverNode = null;

		if ($popover.hasClass('pkpTagit')) {
			$popoverNode = $popover.find(':input').filter(':visible');
		} else {
			$popoverNode = $popover.find(':input').first();
		}

		$popoverNode
				.focus(this.callbackWrapper(this.multilingualShow));
		// Bind to the blur of any of the inputs to to check if we should close.
		$popover.find(':input').
				blur(this.callbackWrapper(this.multilingualHide));

		this.bind('tinyMCEInitialized', this.callbackWrapper(this.handleTinyMCEEvents_));
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.form.MultilingualInputHandler,
			$.pkp.classes.Handler);


	//
	// Private properties
	//
	/**
	 * This timer is used to control closing the
	 * popover when blur events are detected.
	 * @private
	 * @type {Object}
	 */
	$.pkp.controllers.form.MultilingualInputHandler.prototype.
			popoverCloseTimer_ = null;


	//
	// Public methods
	//
	/**
	 * Internal callback called to show additional languages for a
	 * multilingual input
	 *
	 * @param {HTMLElement} multilingualInput The primary multilingual
	 *		element in the set to show.
	 * @param {Event} event The event that triggered the action.
	 */
	$.pkp.controllers.form.MultilingualInputHandler.prototype.multilingualShow =
			function(multilingualInput, event) {

		var $popover = this.getHtmlElement();
		$popover.addClass('localization_popover_container_focus');

		// Hack alert: setting width in JS because they do not line up otherwise.
		$popover.find('.localization_popover').width($popover.width());

		// Show the pop over.
		$popover.find('.localization_popover').show();
	};


	/**
	 * Internal callback called to hide additional languages for a
	 * multilingual input
	 *
	 * @param {HTMLElement} multilingualInput The element in the
	 *		multilingual set to hide.
	 * @param {Event} event The event that triggered the action.
	 */
	$.pkp.controllers.form.MultilingualInputHandler.prototype.multilingualHide =
			function(multilingualInput, event) {

		// Use a timeout to give the other element a chance to acquire the focus.
		setTimeout(this.callbackWrapper(function() {
			var $popover = this.getHtmlElement();
			var found = false;
			// Test if any of the other elements has the focus.
			$popover.find(':input').each(function(index, elem) {
				if (elem === document.activeElement) {
					found = true;
				}
			});
			// If none of them have the focus, we can hide the pop over.
			if (!found) {
				$popover.removeClass('localization_popover_container_focus');
				$popover.find('.localization_popover').hide();
			}
		}), 0);
	};


	/**
	 * tinyMCE initialized event handler, it will attach focus and blur
	 * event handlers to the tinyMCE window element.
	 * @param {HTMLElement} context The parent context element.
	 * @param {HTMLElement} input The input element that triggered the
	 * event.
	 * @param {Event} event The tinyMCE initialized event.
	 * @param {Object} tinyMCEObject The tinyMCE object inside this
	 * multilingual element handler that was initialized.
	 */
	$.pkp.controllers.form.MultilingualInputHandler.prototype.handleTinyMCEEvents_ =
			function(context, input, event, tinyMCEObject) {
		var editorId = tinyMCEObject.editorId;
		$(tinyMCEObject.getWin()).focus(
				this.callbackWrapper(function() {

			// Create a callback for the set content event, so we can
			// still show the multilingual input if user is back from an
			// image insertion, html edit or fullscreen mode.
			var setContentCallback = this.callbackWrapper(
					function(tinyMCEObject) {
				var $tinyWindow = $(tinyMCEObject.getWin());
				if (!this.getHtmlElement().
						hasClass('localization_popover_container_focus')) {
					$tinyWindow.focus();
				};
			});

			// Make sure we don't have more than one set content handler.
			tinyMCEObject.onSetContent.remove(setContentCallback);

			// Add the set content callback.
			tinyMCEObject.onSetContent.add(setContentCallback);

			clearTimeout(this.popoverTimer);
			var $popoverContainer = this.getHtmlElement();
			$popoverContainer.
				addClass('localization_popover_container_focus');
			var $localizationPopover = $popoverContainer.find('.localization_popover');

			$localizationPopover.find('iframe').width($popoverContainer.width() -1);
			$localizationPopover.show();
	    }));
		$(tinyMCEObject.getWin()).blur(
				this.callbackWrapper(function() {
			// set a short timer to prevent the next popover from closing.
			// this allows time for the next click event from the
			// TinyMCE editor to cancel the timer.
			this.popoverTimer = setTimeout(this.callbackWrapper(
					function() {
				this.getHtmlElement().
					removeClass('localization_popover_container_focus');
				$('.localization_popover', this.getHtmlElement()).hide();
			}), 0);
	    }));
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);
