/**
 * @file js/controllers/form/MultilingualInputHandler.js
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
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

		this.parent($popover, options);
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
	 * External hook for the 'pkpmultilingualpopover' plugin for TinyMCE.
	 *
	 * This method is called when an event is triggered within the editor.
	 * We are only interested in click or keyup events and must re-examine
	 * the DOM to find the element of interest since TinyMCE re-writes the
	 * structure.
	 *
	 * @param {String} editorId A string representing the id attribute of
	 *  the field which has been converted into a TinyMCE instance.  This
	 *  corresponds to the field names on the form.  e.g., 'abstract'.
	 * @param {Event} event An Event object representing the event which
	 *  occurred within the TinyMCE window's own DOM.
	 */
	$.pkp.controllers.form.MultilingualInputHandler.prototype.receiveEditorEvent =
			function(editorId, event) {
		if (event.type == 'click' || event.type == 'keyup') {
			clearTimeout(this.popoverTimer);
			var $parentElement = $('#' + editorId).parent();
			$parentElement.find('div[class="localization_popover"] iframe').
					width($parentElement.width());
			$('#' + editorId).parent().find('.localization_popover').show();
		} else if (event.type == 'blur') {
			// set a short timer to prevent the next popover from closing.
			// this allows time for the next click event from the
			// TinyMCE editor to cancel the timer.
			this.popoverTimer = setTimeout(function() {
				$('.localization_popover').hide();
			}, 500);
		}
	};


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

/** @param {jQuery} $ jQuery closure. */
})(jQuery);
