/**
 * @file js/controllers/form/MultilingualInputHandler.js
 *
 * Copyright (c) 2000-2011 John Willinsky
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
		$popover.find(':input').first()
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
	// Public methods
	//
	
	/**
	 * External hook for the 'pkpmultilingualpopover' plugin for TinyMCE.
	 * 
	 * This method is called when an event is triggered within the editor.  We
	 * are only interested in click or keyup events and must re-examine the DOM
	 * to find the element of interest since TinyMCE re-writes the structure.
	 * 
	 * @param {String} editorId A string representing the id attribute of the field
	 *		which has been converted into a TinyMCE instance.  This corresponds to the field 
	 *		names on the form.  e.g., 'abstract'.
	 * @param {Event} event An Event object representing the event which occurred within
	 *		the TinyMCE window's own DOM.
	 */
	
	$.pkp.controllers.form.MultilingualInputHandler.receiveEditorEvent = 
		function(editorId, event) {
			if (event.type == 'click' || event.type == 'keyup') {

				$('#' + editorId).parent().find('.localization_popover').
					width($('#' + editorId).parent().width());

				$('#' + editorId).parent().find('.localization_popover').show();
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
